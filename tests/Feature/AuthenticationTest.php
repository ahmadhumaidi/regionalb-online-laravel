<?php

namespace Tests\Feature;

use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_active_regional_a_user_can_log_in(): void
    {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
        ]);

        $user = RsmUser::create([
            'name' => 'Test Staff Regional A',
            'username' => 'test_staff_regional_a',
            'password_hash' => Hash::make('password-test'),
            'role' => RsmUser::ROLE_STAFF,
            'jabatan' => 'Staff Unit',
            'area' => 'Regional A',
            'regional' => 'Regional 1',
            'is_active' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password-test',
        ]);

        $response->assertRedirect(route('profile'));
        $this->assertAuthenticatedAs($user);
    }
}
