<?php

namespace Tests\Feature;

use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BdcUsersPageTest extends TestCase
{
    public function test_bdc_users_page_renders(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110004_create_rsm_bdc_report_user_snapshots_table.php',
        ]]);

        Http::fake([
            '*' => Http::response('{}', 200),
        ]);

        $user = RsmUser::create([
            'id' => 900010, 'name' => 'Test Senior', 'username' => 'test_senior_900010',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/bdc-users');

        $response->assertOk();

        $user->delete();
    }
}
