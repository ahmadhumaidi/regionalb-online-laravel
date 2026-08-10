<?php

namespace Tests\Feature;

use App\Models\RsmSocialAccount;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ContentSummaryScopeTest extends TestCase
{
    public function test_konten_page_fuzzy_matches_staff_to_own_campus(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105957_create_rsm_social_accounts_table.php',
            'database/migrations/2026_08_05_105958_create_rsm_social_posts_table.php',
        ]]);

        $staff = RsmUser::create([
            'id' => 900012, 'name' => 'Test Staff', 'username' => 'test_staff_900012',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6',
            // rsm_users spells it with the parenthetical abbreviation - the
            // account below spells it plainly, the exact mismatch
            // CampusMatcher exists to bridge.
            'campus_name' => 'Universitas Patria Artha ( UPA )', 'is_active' => true,
        ]);

        $ownAccount = RsmSocialAccount::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 6', 'unit_name' => 'Universitas Patria Artha',
            'instagram_username' => 'upa_official', 'is_active' => true,
        ]);
        $otherAccount = RsmSocialAccount::create([
            'area' => 'Regional B', 'wilayah' => 'Regional 6', 'unit_name' => 'Universitas Lain',
            'instagram_username' => 'lain_official', 'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->get('/konten');

        $response->assertOk();
        $response->assertSee('upa_official');
        $response->assertDontSee('lain_official');

        $ownAccount->delete();
        $otherAccount->delete();
        $staff->delete();
    }
}
