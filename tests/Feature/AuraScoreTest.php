<?php

namespace Tests\Feature;

use App\Models\RsmDailyMissionClaim;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * "Skor Performa" (Profile page's Aura card) - now Energy-based (Daily
 * Mission's own currency, separate from the XP ledger) instead of report/
 * lead/closing volume. See ProfileController::show(): Aura = min(100,
 * round(monthEnergy / 60)) - Daily Mission's monthly chest tops out at
 * 6000 energy (ProfileController::MONTHLY_CHEST_TIERS), so /60 maps a
 * maxed-out month to exactly 100.
 */
class AuraScoreTest extends TestCase
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

    private function makeStaff(int $id, string $name): RsmUser
    {
        return RsmUser::create([
            'id' => $id, 'name' => $name, 'username' => 'test_aura_'.$id,
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
    }

    /** No claims this month at all - Aura reads 0, not some report-based fallback. */
    public function test_aura_is_zero_with_no_energy_claimed_this_month(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(950001, 'No Energy Staff');

        $response = $this->actingAs($staff)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('0/100');

        $staff->delete();
    }

    /** 300 energy this month -> round(300/60) = 5. */
    public function test_aura_scales_with_energy_claimed_this_month(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(950002, 'Some Energy Staff');
        RsmDailyMissionClaim::create(['user_id' => $staff->id, 'mission_key' => 'login', 'claim_date' => now()->toDateString(), 'energy' => 300, 'stars' => 0]);

        $response = $this->actingAs($staff)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('5/100');

        $staff->delete();
    }

    /** A claim from last month must not count toward this month's Aura. */
    public function test_aura_only_counts_energy_claimed_this_month(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(950003, 'Last Month Staff');
        RsmDailyMissionClaim::create([
            'user_id' => $staff->id, 'mission_key' => 'login',
            'claim_date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'energy' => 600, 'stars' => 0,
        ]);

        $response = $this->actingAs($staff)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('0/100');

        $staff->delete();
    }

    /** A maxed-out month (well past the 6000 monthly chest ceiling) caps Aura at 100, doesn't overflow past it. */
    public function test_aura_caps_at_100(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(950004, 'Maxed Energy Staff');
        RsmDailyMissionClaim::create(['user_id' => $staff->id, 'mission_key' => 'login', 'claim_date' => now()->toDateString(), 'energy' => 9000, 'stars' => 0]);

        $response = $this->actingAs($staff)->get(route('profile'));

        $response->assertOk();
        $response->assertSee('100/100');

        $staff->delete();
    }
}
