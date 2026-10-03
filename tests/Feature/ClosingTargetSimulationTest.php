<?php

namespace Tests\Feature;

use App\Models\RsmCollabDailyMetric;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ClosingTargetSimulationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110009_create_rsm_collab_daily_metrics_table.php',
        ]]);
    }

    public function test_only_super_user_can_open_simulation(): void
    {
        $staff = RsmUser::create(['name' => 'Staff Simulasi', 'username' => 'staff_simulasi', 'password_hash' => 'x', 'role' => RsmUser::ROLE_STAFF, 'jabatan' => 'Staff', 'is_active' => true]);
        $this->actingAs($staff)->get('/simulasi-target-closing')->assertForbidden();
    }

    public function test_super_user_sees_monthly_achievement_and_exact_target_allocation(): void
    {
        $superUser = RsmUser::create(['name' => 'Super Simulasi', 'username' => 'super_simulasi', 'password_hash' => 'x', 'role' => RsmUser::ROLE_SUPER_USER, 'jabatan' => 'Super User', 'is_active' => true]);
        foreach ([['2026-01-10', 10], ['2026-02-10', 30]] as [$date, $value]) {
            RsmCollabDailyMetric::create(['report_name' => 'Closing Kampus Regional', 'metric_date' => $date, 'entity_key' => 'kampus:test', 'regional' => 'Regional 1', 'campus_name' => 'Kampus Test', 'value' => $value]);
        }

        $response = $this->actingAs($superUser)->get('/simulasi-target-closing?from_month=2026-01&to_month=2026-02&target=101&campuses[]=Kampus%20Test');
        $response->assertOk()->assertSee('Kampus Test')->assertViewHas('grandTotal', 40.0)
            ->assertViewHas('months', fn (array $months): bool => array_sum(array_column($months, 'target')) === 101 && $months[0]['target'] === 25 && $months[1]['target'] === 76);
    }
}
