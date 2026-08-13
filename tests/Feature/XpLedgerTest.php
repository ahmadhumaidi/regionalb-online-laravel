<?php

namespace Tests\Feature;

use App\Models\RsmAdLead;
use App\Models\RsmGamificationTransaction;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\GamificationService;
use App\Services\Dashboard\XpService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gamification Refactor Phase 1: "Lifetime XP Ledger + Level Progression".
 * See XpService, RsmGamificationTransaction, and the
 * gamification:backfill-xp console command.
 */
class XpLedgerTest extends TestCase
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
        ]]);
    }

    private function makeStaff(int $id, string $name): RsmUser
    {
        return RsmUser::create([
            'id' => $id, 'name' => $name, 'username' => 'test_xp_'.$id,
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
    }

    /** 1. XP transaction berhasil dibuat. */
    public function test_award_xp_creates_a_transaction(): void
    {
        $this->migrate();
        $user = $this->makeStaff(920001, 'Award Staff');

        $tx = XpService::awardXp($user, 'manual_award', 50, 'test', 1, 'unit test award');

        $this->assertDatabaseHas('rsm_gamification_transactions', [
            'id' => $tx->id, 'user_id' => $user->id, 'event_type' => 'manual_award', 'xp' => 50,
        ]);

        $user->delete();
    }

    /** 2. Event yang sama tidak bisa award XP dua kali. */
    public function test_award_xp_is_idempotent_for_the_same_event(): void
    {
        $this->migrate();
        $user = $this->makeStaff(920002, 'Idempotent Staff');

        $first = XpService::awardXp($user, 'manual_award', 30, 'test', 5);
        $second = XpService::awardXp($user, 'manual_award', 30, 'test', 5);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RsmGamificationTransaction::where('user_id', $user->id)->count());
        $this->assertSame(30, XpService::getLifetimeXp($user));

        $user->delete();
    }

    /** awardXp() refuses an award with neither a source_id nor an idempotency_key - it could never be deduped. */
    public function test_award_xp_requires_a_source_id_or_idempotency_key(): void
    {
        $this->migrate();
        $user = $this->makeStaff(920003, 'Guard Staff');

        $this->expectException(\InvalidArgumentException::class);
        XpService::awardXp($user, 'manual_award', 10);

        $user->delete();
    }

    /** 3. Lifetime XP = SUM ledger. */
    public function test_lifetime_xp_is_the_sum_of_all_transactions(): void
    {
        $this->migrate();
        $user = $this->makeStaff(920004, 'Sum Staff');

        XpService::awardXp($user, 'a', 100, 'test', 1);
        XpService::awardXp($user, 'b', 250, 'test', 2);
        XpService::awardXp($user, 'c', 7, 'test', 3);

        $this->assertSame(357, XpService::getLifetimeXp($user));

        $user->delete();
    }

    /** 4. Level 1 bekerja + 9. user tanpa transaksi tetap aman. */
    public function test_zero_xp_and_no_transactions_is_safely_level_1(): void
    {
        $this->migrate();
        $user = $this->makeStaff(920005, 'Fresh Staff');

        $this->assertSame(0, XpService::getLifetimeXp($user));

        $calc = XpService::calculateLevel(0);
        $this->assertSame(1, $calc['level']);
        $this->assertSame(0, $calc['xp_into_level']);
        $this->assertSame(0, $calc['progress_percent']);
        $this->assertGreaterThan(0, $calc['xp_needed']);

        $user->delete();
    }

    /** 5. Level progression naik secara benar (monotonic, and crossing a threshold bumps the level). */
    public function test_level_increases_monotonically_with_xp(): void
    {
        $this->migrate();

        $levelAt100 = XpService::calculateLevel(100)['level'];
        $levelAt5000 = XpService::calculateLevel(5000)['level'];
        $levelAt20000 = XpService::calculateLevel(20000)['level'];

        $this->assertGreaterThanOrEqual($levelAt100, $levelAt5000);
        $this->assertGreaterThan($levelAt5000, $levelAt20000);

        // Crossing exactly into the next level's start XP must report that next level.
        $calc = XpService::calculateLevel(100);
        $this->assertSame(2, $calc['level']);
        $this->assertSame(0, $calc['xp_into_level']);
    }

    /** 6. progress_percent tidak <0 atau >100. */
    public function test_progress_percent_always_within_bounds(): void
    {
        $this->migrate();

        foreach ([0, 1, 99, 100, 101, 5000, 12345, 999999] as $xp) {
            $percent = XpService::calculateLevel($xp)['progress_percent'];
            $this->assertGreaterThanOrEqual(0, $percent, "xp={$xp}");
            $this->assertLessThanOrEqual(100, $percent, "xp={$xp}");
        }
    }

    /** 7. XP 30k-40k tidak menghasilkan level ratusan. */
    public function test_thirty_to_forty_thousand_xp_does_not_produce_hundreds_of_levels(): void
    {
        $this->migrate();

        $level30k = XpService::calculateLevel(30000)['level'];
        $level40k = XpService::calculateLevel(40000)['level'];

        $this->assertLessThan(100, $level30k);
        $this->assertLessThan(100, $level40k);
        $this->assertGreaterThan(1, $level40k);
        $this->assertGreaterThanOrEqual($level30k, $level40k);
    }

    /** 8. legacy backfill tidak menggandakan XP. */
    public function test_backfill_command_is_idempotent(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(920006, 'Backfill Staff');
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Backfill Staff', 'created_by_role' => 'staff',
            'status' => 'Diverifikasi', 'title' => 'Backfill Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Backfill Campaign',
            'budget_requested' => 100000, 'realization_amount' => 100000,
        ]);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Lead 1', 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar']);

        Artisan::call('gamification:backfill-xp');
        $xpAfterFirstRun = XpService::getLifetimeXp($staff->fresh());
        $this->assertGreaterThan(0, $xpAfterFirstRun);

        Artisan::call('gamification:backfill-xp');
        $xpAfterSecondRun = XpService::getLifetimeXp($staff->fresh());

        $this->assertSame($xpAfterFirstRun, $xpAfterSecondRun);
        $this->assertSame(
            1,
            RsmGamificationTransaction::where('user_id', $staff->id)->where('event_type', 'legacy_xp_import')->count()
        );

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    public function test_backfill_command_dry_run_writes_nothing(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(920007, 'Dry Run Staff');
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Dry Run Staff', 'created_by_role' => 'staff',
            'status' => 'Diverifikasi', 'title' => 'Dry Run Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Dry Run Campaign',
            'budget_requested' => 100000, 'realization_amount' => 100000,
        ]);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Lead 1', 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar']);

        Artisan::call('gamification:backfill-xp', ['--dry-run' => true]);

        $this->assertSame(0, RsmGamificationTransaction::where('user_id', $staff->id)->count());

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    /**
     * Gamification Phase 1, section 9: a koordinator's personal lifetime XP
     * must be their own activity only, never their team's pooled total -
     * replaces the old pooled-points expectation.
     */
    public function test_koordinator_personal_xp_excludes_pooled_team_activity(): void
    {
        $this->migrate();
        $koordinator = RsmUser::create([
            'id' => 920008, 'name' => 'Korwil Personal XP', 'username' => 'test_korwil_xp_920008',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);

        $reports = [];
        foreach (['Staff A XP', 'Staff B XP'] as $name) {
            $report = RsmReport::create([
                'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
                'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => $name, 'created_by_role' => 'staff',
                'status' => 'Diverifikasi', 'title' => "Campaign {$name}", 'platform' => 'Meta Ads', 'campaign_name' => "Campaign {$name}",
                'budget_requested' => 100000, 'realization_amount' => 100000,
            ]);
            RsmAdLead::create(['report_id' => $report->id, 'lead_name' => "{$name} Reg", 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar']);
            $reports[] = $report;
        }

        // The koordinator has no report of their own (staff_name/user_id
        // never matches them) - personal XP must be 0, not the team's total.
        $this->assertSame(0, GamificationService::personalProfileXp($koordinator));

        foreach ($reports as $report) {
            RsmAdLead::where('report_id', $report->id)->delete();
            $report->delete();
        }
        $koordinator->delete();
    }

    /**
     * Koordinator/senior tier XP is their team's pooled total averaged
     * across active staff headcount, not the raw sum and not 0 - 10 staff
     * who together closed 100 registrations means 10 registrations'
     * worth of credit per head, matching the koordinator's own stated
     * expectation for this formula.
     */
    public function test_koordinator_xp_is_team_pooled_total_averaged_by_staff_headcount(): void
    {
        $this->migrate();
        $koordinator = RsmUser::create([
            'id' => 920011, 'name' => 'Korwil Average XP', 'username' => 'test_korwil_avg_920011',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);

        $staffAccounts = [];
        $reports = [];
        for ($i = 0; $i < 10; $i++) {
            $name = "Team Staff {$i}";
            $staffAccounts[] = RsmUser::create([
                'id' => 920100 + $i, 'name' => $name, 'username' => 'test_team_staff_'.(920100 + $i),
                'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
                'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
            ]);
            $report = RsmReport::create([
                'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
                'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => $name, 'created_by_role' => 'staff',
                'status' => 'Diverifikasi', 'title' => "Campaign {$name}", 'platform' => 'Meta Ads', 'campaign_name' => "Campaign {$name}",
                'budget_requested' => 100000, 'realization_amount' => 100000,
            ]);
            // 10 registrasi per staff x 10 staff = 100 total closing across the team.
            for ($j = 0; $j < 10; $j++) {
                RsmAdLead::create(['report_id' => $report->id, 'lead_name' => "{$name} Reg {$j}", 'closing_status' => 'Registrasi']);
            }
            $reports[] = $report;
        }

        // Per staff: report_total(1)*5 + approved_reports(1)*10 +
        // leads_total(10)*2 + closing(10)*20 + closing_iklan(10)*10 = 335
        // points (an ads-type report with a verified status counts its
        // registrasi leads toward both the "closing" and ad-specific
        // "closing_iklan" indicators - existing scoreRow() behavior,
        // unchanged by this refactor). Pooled across 10 staff = 3350;
        // averaged by headcount (10) = 335 - the same number as a single
        // staff member's own total, not the pooled 3350.
        $this->assertSame(335, GamificationService::averageTeamProfileXp($koordinator));

        foreach ($reports as $report) {
            RsmAdLead::where('report_id', $report->id)->delete();
            $report->delete();
        }
        foreach ($staffAccounts as $staffAccount) {
            $staffAccount->delete();
        }
        $koordinator->delete();
    }

    /** 10 & 11. ProfileController still renders fine (new XP ledger wired in) and Daily Mission is untouched. */
    public function test_profile_page_renders_with_lifetime_xp_and_daily_mission_intact(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(920009, 'Profile Render Staff');
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'user_id' => $staff->id, 'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'Profile Render Staff', 'created_by_role' => 'staff', 'status' => 'Diverifikasi',
            'title' => 'Render Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Render Campaign',
            'budget_requested' => 100000, 'realization_amount' => 100000,
        ]);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Lead 1', 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar']);

        $response = $this->actingAs($staff)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('XP');
        $response->assertSee('Level');
        $response->assertSee('Daily Mission');
        $response->assertSee('Login');

        // Visiting again the same day must not double-bank the sync delta.
        $xpAfterFirstVisit = XpService::getLifetimeXp($staff->fresh());
        $this->actingAs($staff)->get(route('profile'))->assertOk();
        $this->assertSame($xpAfterFirstVisit, XpService::getLifetimeXp($staff->fresh()));

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    /**
     * Gap fallback Collab: a staff member with zero Collab rows (never
     * matched by name in rsm_collab_daily_metrics) used to score 0 for the
     * registrasi/herreg slice regardless of real ad_leads activity - neither
     * the real-time report/lead events nor the Collab-only sync covered it.
     * GamificationService::personalRegistrasiHerregXp() now falls back to
     * this staff's own ad_leads-derived registrasi/herreg count when Collab
     * has nothing for them, mirroring scoreRow()'s leaderboard formula.
     */
    public function test_collab_absent_staff_earns_registrasi_herreg_xp_from_ad_leads_fallback(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(920012, 'Fallback Only Staff');

        // First-ever sync call always just records a zero-XP baseline
        // (existing syncCollabActivity() design - avoids double-counting
        // whatever the legacy backfill already captured at cutover time).
        XpService::syncPersonalActivity($staff);
        $this->assertSame(0, XpService::getLifetimeXp($staff));

        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Fallback Only Staff', 'created_by_role' => 'staff',
            'status' => 'Diverifikasi', 'title' => 'Fallback Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Fallback Campaign',
            'budget_requested' => 100000, 'realization_amount' => 100000,
        ]);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Reg 1', 'closing_status' => 'Registrasi']);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Reg 2', 'closing_status' => 'Registrasi']);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Her 1', 'closing_status' => 'Herregistrasi']);

        // No Collab row exists for this staff at all - registrasi_total(3,
        // 2 explicit "Registrasi" plus the "Herregistrasi" lead which
        // ClosingStatusClassifier also counts into the registrasi bucket -
        // existing scoreRow() behavior, unchanged here)*20 + herreg(1)*35 =
        // 95 must come from ad_leads instead of scoring 0.
        XpService::syncPersonalActivity($staff);
        $this->assertSame(95, XpService::getLifetimeXp($staff));
        $this->assertDatabaseHas('rsm_gamification_transactions', [
            'user_id' => $staff->id, 'event_type' => 'collab_xp_sync', 'xp' => 95,
        ]);

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    /**
     * The watermark-based reconciliation (highest target ever observed,
     * only positive deltas awarded) makes the fallback safe even if Collab
     * later starts tracking a staff who used to have none: switching the
     * target's source from ad_leads to Collab can never subtract already-
     * banked XP, and can never re-award growth that source already covered.
     */
    public function test_switching_from_ad_leads_fallback_to_collab_data_never_double_counts(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(920013, 'Source Switch Staff');
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Source Switch Staff', 'created_by_role' => 'staff',
            'status' => 'Diverifikasi', 'title' => 'Switch Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Switch Campaign',
            'budget_requested' => 100000, 'realization_amount' => 100000,
        ]);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Reg 1', 'closing_status' => 'Registrasi']);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Reg 2', 'closing_status' => 'Registrasi']);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Her 1', 'closing_status' => 'Herregistrasi']);

        // Baseline captures the ad_leads-fallback target (registrasi_total 3
        // incl. the double-counted Herregistrasi lead, *20 + herreg 1*35 =
        // 95) as the starting watermark - matches how a brand-new staff
        // with pre-existing activity is treated on their very first sync.
        XpService::syncPersonalActivity($staff);
        $this->assertSame(0, XpService::getLifetimeXp($staff));

        // Collab starts tracking this staff, but with a smaller value than
        // the fallback already reached (1 registrasi = 20 < watermark 95).
        // Must not subtract anything.
        DB::table('rsm_collab_daily_metrics')->insert([
            'report_name' => 'Closing Personal Per Regional', 'metric_date' => now()->subDay()->toDateString(),
            'entity_key' => 'switch-reg-1', 'staff_name' => 'Source Switch Staff', 'regional' => 'Regional 6', 'value' => 1,
        ]);
        XpService::syncPersonalActivity($staff);
        $this->assertSame(0, XpService::getLifetimeXp($staff));
        $this->assertSame(
            0,
            RsmGamificationTransaction::where('user_id', $staff->id)->where('event_type', 'collab_xp_sync')->count()
        );

        // Collab grows past the old watermark (5 registrasi = 100 > 95) -
        // only the excess (5) is awarded, not the full 100 again. Same
        // entity_key as the first insert so staffTotals()'s SUM(value)
        // grouped by entity_key combines them (a different entity_key would
        // instead replace the first row's contribution when re-keyed by
        // staff name, not add to it).
        DB::table('rsm_collab_daily_metrics')->insert([
            'report_name' => 'Closing Personal Per Regional', 'metric_date' => now()->toDateString(),
            'entity_key' => 'switch-reg-1', 'staff_name' => 'Source Switch Staff', 'regional' => 'Regional 6', 'value' => 4,
        ]);
        XpService::syncPersonalActivity($staff);
        $this->assertSame(5, XpService::getLifetimeXp($staff));

        DB::table('rsm_collab_daily_metrics')->where('staff_name', 'Source Switch Staff')->delete();
        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    /** 12. GamificationService::build() (Dashboard "Arena Performa Staff") tetap compatible - unrelated to the XP ledger. */
    public function test_gamification_build_still_works_unrelated_to_xp_ledger(): void
    {
        $this->migrate();
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_110000_create_rsm_monthly_targets_table.php',
            'database/migrations/2026_08_11_100003_add_indicator_targets_to_rsm_monthly_targets_table.php',
        ]]);

        $senior = RsmUser::create([
            'id' => 920010, 'name' => 'Build Compat Senior', 'username' => 'test_build_compat_920010',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);

        $result = GamificationService::build(
            'Regional B',
            ['date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString(), 'wilayah' => '', 'unit_name' => '', 'staff_name' => ''],
            $senior
        );

        $this->assertArrayHasKey('leaderboard', $result);
        $this->assertArrayHasKey('my_rank', $result);
        $this->assertArrayHasKey('challenge', $result);
        $this->assertArrayHasKey('point_rules', $result);

        $senior->delete();
    }
}
