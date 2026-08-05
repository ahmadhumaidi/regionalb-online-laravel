<?php

namespace Tests\Feature;

use App\Models\RsmUser;
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
}
