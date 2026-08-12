<?php

namespace Tests\Feature;

use App\Models\RsmAdLead;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\XpService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Profile page's Level/XP/League/Badge numbers now come from the same
 * GamificationService formula as the Dashboard's "Arena Performa Staff"
 * leaderboard (see GamificationService::profileSummary()), instead of a
 * separate ad-hoc calculation. These tests pin down the exact numbers so a
 * future change to either side doesn't silently break the other.
 */
class ProfileGamificationTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_09_120000_add_insight_attachment_path_to_rsm_reports_table.php',
            'database/migrations/2026_08_05_105956_create_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_05_105959_create_rsm_ad_leads_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
            'database/migrations/2026_08_05_110009_create_rsm_collab_daily_metrics_table.php',
            'database/migrations/2026_08_12_150000_create_rsm_daily_mission_claims_table.php',
            'database/migrations/2026_08_13_090000_create_rsm_gamification_transactions_table.php',
            'database/migrations/2026_08_05_110002_create_rsm_coordinator_schedules_table.php',
        ]]);
    }

    public function test_staff_sees_own_points_and_badges_on_profile_page(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 900030, 'name' => 'Test Staff Gamif', 'username' => 'test_staff_900030',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff Gamif', 'created_by_role' => 'staff',
            'status' => 'Diverifikasi', 'title' => 'Gamif Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Gamif Campaign',
            'budget_requested' => 500000, 'realization_amount' => 500000,
        ]);
        for ($i = 0; $i < 3; $i++) {
            RsmAdLead::create(['report_id' => $report->id, 'lead_name' => "Reg {$i}", 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar', 'notes' => 'Lengkap']);
        }
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Her 1', 'closing_status' => 'Herregistrasi', 'follow_up_result' => 'Sudah her', 'notes' => 'Lengkap']);
        for ($i = 0; $i < 6; $i++) {
            RsmAdLead::create(['report_id' => $report->id, 'lead_name' => "Lead {$i}", 'follow_up_result' => 'Masih proses']);
        }

        // Gamification Phase 2: report/lead-driven XP is now awarded in
        // real time at the authoritative mutation point (ReportFormService,
        // AdLeadImportService, etc) instead of ProfileController::show()
        // recalculating the whole formula on every visit - this fixture
        // creates rows directly (bypassing those services), so simulate
        // what they'd have triggered: report_created(5) +
        // report_approved(10, status=Diverifikasi) + lead_created(10)*2=20
        // + lead_follow_up(10)*4=40 + lead_notes_complete(4)*5=20 +
        // closing_iklan(4)*10=40 -> 135 XP. The old formula's
        // registrasi(4)*20 + herreg(1)*35 = 115 doesn't apply here anymore
        // because that component now only comes from Collab data
        // (XpService::syncPersonalActivity()'s daily sync) - this fixture
        // has none seeded, matching a staff member with ad-lead activity
        // but no Collab sync data yet.
        XpService::syncReportEventXp($report->fresh());

        $response = $this->actingAs($staff)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('135 XP');
        $response->assertSee('Level 2');
        $response->assertSee('League Starter');
        $response->assertSee('✓ Follow Up Hero', false);
        $response->assertSee('✓ Closing Hunter', false);
        $response->assertSee('✓ Herregistrasi Champion', false);
        $response->assertSee('✓ Budget Efficient', false);
        $response->assertSee('○ Consistency Streak', false);

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    /**
     * Gamification Phase 1 changed this: the koordinator's Level/XP block
     * now reads their *team-average* lifetime XP ledger (XpService) instead
     * of the raw pooled total - a koordinator's real job is monitoring/
     * approving, not filing reports themselves, so their personal XP is the
     * team's pooled point total divided by their active staff headcount
     * (see GamificationService::averageTeamProfileXp()), not the raw sum.
     * Badges are unaffected by this refactor and still show the team's
     * pooled achievement, since GamificationService::profileSummary()
     * (which drives badges) wasn't touched.
     */
    public function test_koordinator_xp_is_team_average_and_badges_stay_pooled(): void
    {
        $this->migrate();

        $koordinator = RsmUser::create([
            'id' => 900031, 'name' => 'Korwil Gamif', 'username' => 'test_korwil_900031',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);

        $reports = [];
        $staffAccounts = [];
        foreach (['Staff A', 'Staff B'] as $i => $name) {
            $staffAccounts[] = RsmUser::create([
                'id' => 900050 + $i, 'name' => $name, 'username' => 'test_'.\Illuminate\Support\Str::slug($name).'_'.(900050 + $i),
                'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
                'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
            ]);
            $report = RsmReport::create([
                'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
                'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => $name, 'created_by_role' => 'staff',
                'status' => 'Diverifikasi', 'title' => "Campaign {$name}", 'platform' => 'Meta Ads', 'campaign_name' => "Campaign {$name}",
                'budget_requested' => 100000, 'realization_amount' => 100000,
            ]);
            for ($j = 0; $j < 2; $j++) {
                RsmAdLead::create(['report_id' => $report->id, 'lead_name' => "{$name} Reg {$j}", 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar']);
            }
            $reports[] = $report;
        }

        // Team pooled total is 174 (87 points/staff x 2, see the original
        // pooled-XP comment this test replaced) across 2 active staff ->
        // 87 XP average, not the raw 174 pooled sum, and not 0.
        $response = $this->actingAs($koordinator)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('87 XP');
        $response->assertSee('Level 1');
        $response->assertSee('League Starter');
        // Badges still reflect the pooled team total - unchanged by this refactor.
        $response->assertSee('✓ Closing Hunter', false);
        $response->assertSee('✓ Budget Efficient', false);
        $response->assertSee('○ Follow Up Hero', false);
        $response->assertSee('○ Herregistrasi Champion', false);
        $response->assertSee('○ Consistency Streak', false);

        foreach ($reports as $report) {
            RsmAdLead::where('report_id', $report->id)->delete();
            $report->delete();
        }
        foreach ($staffAccounts as $staffAccount) {
            $staffAccount->delete();
        }
        $koordinator->delete();
    }

    public function test_profile_shows_daily_mission_progress(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 900058, 'name' => 'Daily Mission Staff', 'username' => 'daily_mission_staff',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
        $adReport = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'user_id' => $staff->id, 'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'Daily Mission Staff', 'created_by_role' => 'staff', 'status' => 'Diverifikasi',
            'title' => 'Daily Ads', 'platform' => 'Meta Ads', 'campaign_name' => 'Daily Ads',
            'budget_requested' => 100000,
        ]);
        for ($i = 0; $i < 30; $i++) {
            RsmAdLead::create(['report_id' => $adReport->id, 'lead_name' => "Lead {$i}", 'follow_up_result' => 'Sudah follow up']);
        }
        $otherReport = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => now(),
            'user_id' => $staff->id, 'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'Daily Mission Staff', 'created_by_role' => 'staff', 'status' => 'Dikirim',
            'title' => 'Koordinasi harian',
        ]);
        DB::table('rsm_collab_daily_metrics')->insert([
            'report_name' => 'Share FB Group', 'metric_date' => now()->toDateString(),
            'entity_key' => 'daily-share-1', 'staff_name' => 'Daily Mission Staff', 'regional' => 'Regional 6', 'value' => 3,
        ]);
        DB::table('rsm_collab_daily_metrics')->insert([
            'report_name' => 'Closing Personal Per Regional', 'metric_date' => now()->toDateString(),
            'entity_key' => 'daily-reg-1', 'staff_name' => 'Daily Mission Staff', 'regional' => 'Regional 6', 'value' => 1,
        ]);

        $response = $this->actingAs($staff)->get(route('profile'));

        // Pre-existing test assertions here referenced a "5/5 selesai"
        // summary counter and per-mission "X/Y" progress text that no
        // longer exist since the Daily Mission section was redesigned to a
        // chest-track + individual Claim buttons (all 5 missions in this
        // fixture are done, so each row now renders a Claim button instead
        // of progress text). Updated to match current markup - unrelated to
        // the Gamification Phase 1 XP ledger this test file also covers.
        $response->assertOk();
        $response->assertSee('Daily Mission');
        $response->assertSee('Login');
        $response->assertSee('Follow Up 30');
        $response->assertSee('Share FB');
        $response->assertSee('Aktivitas Lain');
        $response->assertSee('Closing Reg 1');

        \App\Models\RsmCollabDailyMetric::whereIn('entity_key', ['daily-share-1', 'daily-reg-1'])->delete();
        RsmAdLead::where('report_id', $adReport->id)->delete();
        $otherReport->delete();
        $adReport->delete();
        $staff->delete();
    }
}
