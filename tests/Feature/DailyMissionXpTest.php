<?php

namespace Tests\Feature;

use App\Models\RsmGamificationTransaction;
use App\Models\RsmUser;
use App\Services\Dashboard\XpService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Daily Mission-XP unification: claiming a Daily Mission reward now also
 * banks XP into the lifetime ledger (1 energy = 1 XP), on top of the
 * energy/stars currency that already existed - see
 * ProfileController::claimMission().
 */
class DailyMissionXpTest extends TestCase
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
            'id' => $id, 'name' => $name, 'username' => 'test_dmxp_'.$id,
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
    }

    /** The "Login" mission is always done (actual=1, target=1) - claiming it must bank XP equal to its energy reward (10). */
    public function test_claiming_a_mission_awards_xp_equal_to_energy(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(940001, 'Claim XP Staff');

        $this->assertSame(0, XpService::getLifetimeXp($staff));

        $response = $this->actingAs($staff)->post(route('profile.daily-mission.claim', 'login'));

        $response->assertRedirect();
        $this->assertSame(10, XpService::getLifetimeXp($staff));
        $this->assertDatabaseHas('rsm_gamification_transactions', [
            'user_id' => $staff->id, 'event_type' => 'daily_mission_claim', 'source_type' => 'daily_mission_claim', 'xp' => 10,
        ]);

        $staff->delete();
    }

    /** Claiming an already-claimed mission is rejected before it ever reaches awardXp() - no second transaction, no extra XP. */
    public function test_reclaiming_the_same_mission_the_same_day_does_not_double_award_xp(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(940002, 'Reclaim XP Staff');

        $this->actingAs($staff)->post(route('profile.daily-mission.claim', 'login'))->assertRedirect();
        $second = $this->actingAs($staff)->post(route('profile.daily-mission.claim', 'login'));

        $second->assertStatus(422);
        $this->assertSame(10, XpService::getLifetimeXp($staff));
        $this->assertSame(
            1,
            RsmGamificationTransaction::where('user_id', $staff->id)->where('event_type', 'daily_mission_claim')->count()
        );

        $staff->delete();
    }

    /** Koordinator's separate mission scheme (KOORDINATOR_MISSION_REWARDS) claims XP through the same code path. */
    public function test_koordinator_mission_claim_also_awards_xp(): void
    {
        $this->migrate();
        $koordinator = RsmUser::create([
            'id' => 940003, 'name' => 'Claim XP Koordinator', 'username' => 'test_dmxp_koor_940003',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);

        $response = $this->actingAs($koordinator)->post(route('profile.daily-mission.claim', 'login'));

        $response->assertRedirect();
        // Koordinator's "login" reward is 10 energy, same catalog entry as staff's.
        $this->assertSame(10, XpService::getLifetimeXp($koordinator));

        $koordinator->delete();
    }
}
