<?php

namespace Tests\Feature;

use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AboutApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
        ]]);
    }

    public function test_super_user_can_view_about_page_and_download_report(): void
    {
        $superUser = RsmUser::create([
            'name' => 'Super About', 'username' => 'super_about', 'password_hash' => 'x',
            'role' => RsmUser::ROLE_SUPER_USER, 'jabatan' => 'Super User', 'is_active' => true,
        ]);

        $this->actingAs($superUser)->get('/tentang-aplikasi')
            ->assertOk()->assertSee('Laporan Kontribusi Dashboard Regional B');
        $this->actingAs($superUser)->get('/tentang-aplikasi/laporan-kontribusi')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('Laporan_Kontribusi_Dashboard_Regional_Ahmad_Humaidi.pdf');
    }

    public function test_non_super_user_cannot_view_page_or_download_report(): void
    {
        $staff = RsmUser::create([
            'name' => 'Staff About', 'username' => 'staff_about', 'password_hash' => 'x',
            'role' => RsmUser::ROLE_STAFF, 'jabatan' => 'Staff', 'is_active' => true,
        ]);

        $this->actingAs($staff)->get('/tentang-aplikasi')->assertForbidden();
        $this->actingAs($staff)->get('/tentang-aplikasi/laporan-kontribusi')->assertForbidden();
    }
}
