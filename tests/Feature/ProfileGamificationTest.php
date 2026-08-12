<?php

namespace Tests\Feature;

use App\Models\RsmAdLead;
use App\Models\RsmReport;
use App\Models\RsmUser;
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

        // report_total*5=5, approved_reports*10=10, leads_total(10)*2=20,
        // follow_up_total(10)*4=40, closing_for_points(4)*20=80,
        // herreg_for_points(1)*35=35, closing_iklan(4)*10=40,
        // complete_follow_up_notes(4)*5=20 -> 250 points.
        $response = $this->actingAs($staff)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('250 XP');
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

    public function test_koordinator_sees_pooled_wilayah_points_on_profile_page(): void
    {
        $this->migrate();

        $koordinator = RsmUser::create([
            'id' => 900031, 'name' => 'Korwil Gamif', 'username' => 'test_korwil_900031',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);

        $reports = [];
        foreach (['Staff A', 'Staff B'] as $name) {
            $report = RsmReport::create([
                'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
                'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => $name, 'created_by_role' => 'staff',
                'status' => 'Diverifikasi', 'title' => "Campaign {$name}", 'platform' => 'Meta Ads', 'campaign_name' => "Campaign {$name}",
                'budget_requested' => 100000, 'realization_amount' => 100000,
            ]);
            for ($i = 0; $i < 2; $i++) {
                RsmAdLead::create(['report_id' => $report->id, 'lead_name' => "{$name} Reg {$i}", 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar']);
            }
            $reports[] = $report;
        }

        // Per staff: 5 + 10 + 4 + 8 + 40 + 0 + 20 + 0 = 87 points. Pooled
        // across both staff in the koordinator's own wilayah: 174 points.
        $response = $this->actingAs($koordinator)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('174 XP');
        $response->assertSee('Level 1');
        $response->assertSee('League Starter');
        $response->assertSee('✓ Closing Hunter', false);
        $response->assertSee('✓ Budget Efficient', false);
        $response->assertSee('○ Follow Up Hero', false);
        $response->assertSee('○ Herregistrasi Champion', false);
        $response->assertSee('○ Consistency Streak', false);

        foreach ($reports as $report) {
            RsmAdLead::where('report_id', $report->id)->delete();
            $report->delete();
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

        $response->assertOk();
        $response->assertSee('Daily Mission');
        $response->assertSee('5/5 selesai');
        $response->assertSee('Login');
        $response->assertSee('Follow Up');
        $response->assertSee('30/30');
        $response->assertSee('Share FB');
        $response->assertSee('3/3');
        $response->assertSee('Aktivitas Lain');
        $response->assertSee('Closing Reg');

        \App\Models\RsmCollabDailyMetric::whereIn('entity_key', ['daily-share-1', 'daily-reg-1'])->delete();
        RsmAdLead::where('report_id', $adReport->id)->delete();
        $otherReport->delete();
        $adReport->delete();
        $staff->delete();
    }
}
