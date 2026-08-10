<?php

namespace Tests\Feature;

use App\Models\RsmActivityLog;
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
}
