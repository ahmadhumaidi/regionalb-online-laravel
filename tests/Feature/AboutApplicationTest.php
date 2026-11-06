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

    public function test_both_director_roles_can_open_management_features(): void
    {
        foreach ([RsmUser::ROLE_DIRECTOR, RsmUser::ROLE_EXECUTIVE_DIRECTOR] as $role) {
            $director = RsmUser::create([
                'name' => 'Director', 'username' => 'feature_'.$role, 'password_hash' => 'x',
                'role' => $role, 'jabatan' => 'Director', 'is_active' => true,
            ]);
            $this->actingAs($director)->get('/tentang-aplikasi')->assertOk();
            $this->actingAs($director)->get('/tentang-aplikasi/laporan-kontribusi')->assertOk();
            $this->actingAs($director)->get('/laporan-senior/create')->assertOk();
            $this->actingAs($director)->post('/users', [
                'name' => 'Forbidden Super', 'username' => 'forbidden_super_'.$role,
                'user_role' => 'super_user', 'new_user_password' => 'secret123',
            ])->assertSessionHasErrors('user_role');
        }
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
