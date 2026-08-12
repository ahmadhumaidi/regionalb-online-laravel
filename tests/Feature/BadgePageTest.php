<?php

namespace Tests\Feature;

use App\Models\RsmBadgeSetting;
use App\Models\RsmUser;
use App\Services\Dashboard\GamificationService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BadgePageTest extends TestCase
{
    public function test_authenticated_user_can_see_badge_definitions(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_12_090000_create_rsm_badge_settings_table.php',
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
        $response->assertSee('Kampus Growth');
        $response->assertSee('Share FB Booster');
        $response->assertSee('Affiliator Non Mahasiswa');
        $response->assertSee('On Progress');

        $user->delete();
    }

    public function test_manager_can_update_badge_target_values(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_12_090000_create_rsm_badge_settings_table.php',
        ]]);

        $user = RsmUser::create([
            'id' => 900048,
            'name' => 'Badge Manager',
            'username' => 'test_badge_manager_900048',
            'password_hash' => 'x',
            'role' => 'super_user',
            'jabatan' => 'Super User',
            'area' => 'Regional B',
            'is_active' => true,
        ]);

        $settings = collect(GamificationService::badgeDefinitions())
            ->mapWithKeys(fn (array $badge) => [$badge['key'] => (int) $badge['target_value']])
            ->all();
        $settings['follow_up_hero'] = 12;
        $settings['budget_efficient'] = 3;

        $response = $this->actingAs($user)->post('/badges', ['settings' => $settings]);

        $response->assertRedirect('/badges');
        $this->assertSame(12.0, (float) RsmBadgeSetting::where('badge_key', 'follow_up_hero')->value('target_value'));

        $response = $this->actingAs($user)->get('/badges');
        $response->assertSee('Minimal 12 follow up lead');
        $response->assertSee('minimal 3 registrasi');

        $user->delete();
    }
}
