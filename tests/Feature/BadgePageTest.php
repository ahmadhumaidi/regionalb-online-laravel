<?php

namespace Tests\Feature;

use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BadgePageTest extends TestCase
{
    public function test_authenticated_user_can_see_badge_definitions(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
        ]]);

        $user = RsmUser::create([
            'id' => 900047,
            'name' => 'Badge Viewer',
            'username' => 'test_badge_viewer_900047',
            'password_hash' => 'x',
            'role' => 'staff',
            'jabatan' => 'Staff Unit',
            'area' => 'Regional B',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/badges');

        $response->assertOk();
        $response->assertSee('Badge &amp; Achievement', false);
        $response->assertSee('Follow Up Hero');
        $response->assertSee('Minimal 10 follow up lead');
        $response->assertSee('On Progress');

        $user->delete();
    }
}
