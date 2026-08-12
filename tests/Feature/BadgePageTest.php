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
            'database/migrations/2026_08_12_092000_add_indicator_key_to_rsm_badge_settings_table.php',
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
        $response->assertSee('Minimal 10 FU');
        $response->assertSee('Indikator');
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
            'database/migrations/2026_08_12_092000_add_indicator_key_to_rsm_badge_settings_table.php',
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
            ->mapWithKeys(fn (array $badge) => [$badge['key'] => [
                'indicator_key' => $badge['indicator_key'],
                'target_value' => (int) $badge['target_value'],
            ]])
            ->all();
        $settings['follow_up_hero']['indicator_key'] = 'closing_iklan';
        $settings['follow_up_hero']['target_value'] = 12;
        $settings['budget_efficient']['indicator_key'] = 'reg';
        $settings['budget_efficient']['target_value'] = 3;

        $response = $this->actingAs($user)->post('/badges', ['settings' => $settings]);

        $response->assertRedirect('/badges');
        $followUpHero = RsmBadgeSetting::where('badge_key', 'follow_up_hero')->first();
        $this->assertSame('closing_iklan', $followUpHero->indicator_key);
        $this->assertSame(12.0, (float) $followUpHero->target_value);

        $response = $this->actingAs($user)->get('/badges');
        $response->assertSee('Minimal 12 Closing Iklan');
        $response->assertSee('Minimal 3 Reg');

        $user->delete();
    }
}
