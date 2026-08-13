<?php

namespace Tests\Feature;

use App\Models\RsmUser;
use App\Services\BdcReportUsersService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BdcUsersPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // BdcReportUsersService::snapshot() memoizes per PHP process to
        // avoid re-fetching within one real HTTP request; that static
        // otherwise leaks between test methods sharing this test run.
        $property = new \ReflectionProperty(BdcReportUsersService::class, 'requestSnapshot');
        $property->setAccessible(true);
        $property->setValue(null, null);
        Storage::disk('local')->delete('bdc_report_users.json');
    }

    public function test_bdc_users_page_renders(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110004_create_rsm_bdc_report_user_snapshots_table.php',
        ]]);
        DB::table('rsm_bdc_report_user_snapshots')->delete();

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

    public function test_bdc_users_page_scopes_staff_to_own_campus(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_110004_create_rsm_bdc_report_user_snapshots_table.php',
        ]]);
        DB::table('rsm_bdc_report_user_snapshots')->delete();

        Http::fake([
            '*' => Http::response([
                'listdata' => [
                    ['wilayah' => 'Regional 6', 'kampus' => 'STIESIA Surabaya', 'nama' => 'Own Staff', 'total' => 5, 'closing' => 2, 'fu_hari_ini' => 1],
                    ['wilayah' => 'Regional 6', 'kampus' => 'Universitas Lain', 'nama' => 'Other Staff', 'total' => 9, 'closing' => 4, 'fu_hari_ini' => 3],
                ],
            ], 200),
        ]);

        $staff = RsmUser::create([
            'id' => 900011, 'name' => 'Test Staff', 'username' => 'test_staff_900011',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        $response = $this->actingAs($staff)->get('/bdc-users');

        $response->assertOk();
        $response->assertSee('Own Staff');
        $response->assertDontSee('Other Staff');

        $staff->delete();
    }
}
