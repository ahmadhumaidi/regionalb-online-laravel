<?php

namespace Tests\Feature;

use App\Models\RsmActivityLog;
use App\Models\RsmAdBudgetLimit;
use App\Models\RsmMonthlyTarget;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function managementPagesProvider(): array
    {
        return [
            'users' => ['/users'],
            'targets' => ['/targets'],
            'personalia' => ['/jadwal-personalia'],
            'collab-source' => ['/sumber-collab'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('managementPagesProvider')]
    public function test_staff_cannot_open_management_pages(string $path): void
    {
        $staff = new RsmUser(['id' => 900001, 'name' => 'Test Staff', 'role' => 'staff', 'area' => 'Regional B', 'is_active' => true]);

        $this->actingAs($staff)->get($path)->assertForbidden();
    }

    public function test_staff_cannot_open_coordinator_schedule_management_actions(): void
    {
        $staff = new RsmUser(['id' => 900002, 'name' => 'Test Staff', 'role' => 'staff', 'area' => 'Regional B', 'is_active' => true]);

        $this->actingAs($staff)->get('/jadwal-koordinator')->assertForbidden();
        $this->actingAs($staff)->post('/jadwal-koordinator/generate', ['month' => '2026-08'])->assertForbidden();
    }

    public function test_only_super_user_can_generate_whatsapp_schedule_report(): void
    {
        $senior = new RsmUser(['id' => 900003, 'name' => 'Test Senior', 'role' => 'senior', 'area' => 'Regional B', 'is_active' => true]);

        $this->actingAs($senior)->post('/jadwal-koordinator/whatsapp', ['month' => '2026-08'])->assertForbidden();
    }

    public function test_super_user_can_update_managed_user_via_edit_form_fields(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
        ]]);

        $superUser = RsmUser::create([
            'id' => 900004, 'name' => 'Test Super', 'username' => 'test_super_900004',
            'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $managedUser = RsmUser::create([
            'id' => 900005, 'name' => 'Baharuddin Muslim', 'username' => 'test_managed_900005',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator',
            'area' => 'Regional B', 'regional' => 'Kaltim', 'is_active' => true,
        ]);

        // Only the fields the edit modal in resources/views/users/index.blade.php actually submits.
        $response = $this->actingAs($superUser)->patch("/users/{$managedUser->id}", [
            'name' => 'Baharuddin Muslim',
            'user_role' => 'koordinator',
            'regional' => 'Kaltim',
            'campus_name' => 'UNU Kaltim',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertSame('UNU Kaltim', $managedUser->fresh()->campus_name);
        $this->assertSame('Regional B', $managedUser->fresh()->area);
        $this->assertNotEmpty($managedUser->fresh()->jabatan);

        $managedUser->delete();
        $superUser->delete();
    }

    public function test_staff_only_sees_own_rows_in_activity_log(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
        ]]);

        $staff = RsmUser::create([
            'id' => 900006, 'name' => 'Test Staff', 'username' => 'test_staff_900006',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $ownLog = RsmActivityLog::create(['area' => 'Regional B', 'actor_user_id' => $staff->id, 'actor_role' => 'staff', 'actor_name' => 'Test Staff', 'action_name' => 'own_action']);
        $otherLog = RsmActivityLog::create(['area' => 'Regional B', 'actor_user_id' => 999999, 'actor_role' => 'staff', 'actor_name' => 'Other Staff', 'action_name' => 'other_action']);

        $response = $this->actingAs($staff)->get('/role');

        $response->assertOk();
        $response->assertSee('own_action');
        $response->assertDontSee('other_action');

        $ownLog->delete();
        $otherLog->delete();
        $staff->delete();
    }

    public function test_koordinator_campus_is_used_when_creating_personal_monthly_target(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105956_create_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_12_093000_add_unit_name_to_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_05_110000_create_rsm_monthly_targets_table.php',
            'database/migrations/2026_08_11_100003_add_indicator_targets_to_rsm_monthly_targets_table.php',
        ]]);

        $superUser = RsmUser::create([
            'id' => 900007, 'name' => 'Test Super Target', 'username' => 'test_super_target_900007',
            'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $koordinator = RsmUser::create([
            'id' => 900008, 'name' => 'Baharuddin Muslim', 'username' => 'test_baharuddin_900008',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator',
            'area' => 'Regional B', 'regional' => 'Kaltim', 'campus_name' => 'UNU Kaltim',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->post('/targets', [
            'target_month' => '2026-08',
            'scope_type' => 'staff',
            'staff_name' => 'Baharuddin Muslim',
            'target_leads' => 10,
            'target_follow_up' => 8,
            'target_registrasi' => 3,
            'target_herregistrasi' => 1,
            'target_anggaran' => 100000,
        ]);

        $response->assertRedirect('/scoring/targets');
        $target = RsmMonthlyTarget::where('staff_name', 'Baharuddin Muslim')->first();
        $this->assertNotNull($target);
        $this->assertSame('Kaltim', $target->wilayah);
        $this->assertSame('UNU Kaltim', $target->unit_name);

        $target->delete();
        $koordinator->delete();
        $superUser->delete();
    }

    public function test_monthly_target_stores_indicator_targets_and_weights(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105956_create_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_12_093000_add_unit_name_to_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_05_110000_create_rsm_monthly_targets_table.php',
            'database/migrations/2026_08_11_100003_add_indicator_targets_to_rsm_monthly_targets_table.php',
        ]]);

        $superUser = RsmUser::create([
            'id' => 900009, 'name' => 'Test Super Indicator', 'username' => 'test_super_indicator_900009',
            'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $staff = RsmUser::create([
            'id' => 900010, 'name' => 'Test Staff Indicator', 'username' => 'test_staff_indicator_900010',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 7', 'campus_name' => 'Universitas Test',
            'is_active' => true,
        ]);
        RsmAdBudgetLimit::updateOrCreate(
            ['area' => 'Regional B', 'ad_period' => 'Agustus 2026', 'wilayah' => 'Regional 7', 'unit_name' => 'Universitas Test'],
            ['budget_limit' => 1500000, 'created_by_user_id' => $superUser->id, 'created_by_name' => $superUser->name]
        );

        $response = $this->actingAs($superUser)->post('/targets', [
            'target_month' => '2026-08',
            'scope_type' => 'staff',
            'staff_name' => 'Test Staff Indicator',
            'indicator_targets' => [
                'reg' => ['target' => 12, 'weight' => 10],
                'herreg' => ['target' => 8, 'weight' => 15],
                'leads' => ['target' => 40, 'weight' => 8],
                'fu' => ['target' => 25, 'weight' => 10],
                'realisasi_iklan' => ['target' => 999999, 'weight' => 7],
            ],
        ]);

        $response->assertRedirect('/scoring/targets');
        $target = RsmMonthlyTarget::where('staff_name', 'Test Staff Indicator')->first();
        $this->assertNotNull($target);
        $this->assertSame(12, (int) $target->target_registrasi);
        $this->assertSame(8, (int) $target->target_herregistrasi);
        $this->assertSame(40, (int) $target->target_leads);
        $this->assertSame(25, (int) $target->target_follow_up);
        $this->assertSame(1500000.0, (float) $target->target_anggaran);
        $this->assertSame(1500000.0, (float) $target->indicator_targets['realisasi_iklan']['target']);
        $this->assertSame(10.0, (float) $target->indicator_targets['reg']['weight']);
        $this->assertSame('Herreg Kampus', $target->indicator_targets['herreg_kampus']['label']);

        $target->delete();
        RsmAdBudgetLimit::where('area', 'Regional B')->where('ad_period', 'Agustus 2026')->where('wilayah', 'Regional 7')->where('unit_name', 'Universitas Test')->delete();
        $staff->delete();
        $superUser->delete();
    }

    public function test_target_and_weight_page_is_available_inside_scoring(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110000_create_rsm_monthly_targets_table.php',
            'database/migrations/2026_08_11_100003_add_indicator_targets_to_rsm_monthly_targets_table.php',
        ]]);

        $superUser = RsmUser::create([
            'id' => 900011, 'name' => 'Test Super Scoring Target', 'username' => 'test_super_scoring_target_900011',
            'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->get('/scoring/targets');

        $response->assertOk();
        $response->assertSee('Target &amp; Bobot', false);
        $response->assertSee('Hasil Scoring');
        $response->assertSee('Otomatis dari plafon');

        $superUser->delete();
    }
}
