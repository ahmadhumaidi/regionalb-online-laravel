<?php

namespace Tests\Feature;

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
}
