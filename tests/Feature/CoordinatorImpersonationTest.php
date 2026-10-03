<?php

namespace Tests\Feature;

use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CoordinatorImpersonationTest extends TestCase
{
    public function test_opening_impersonation_url_redirects_to_dashboard(): void
    {
        $this->migrate();
        $koordinator = $this->makeUser(900069, 'Koorwil Redirect', RsmUser::ROLE_KOORDINATOR, 'Regional 6');

        $this->actingAs($koordinator)
            ->get('/impersonation')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('notice');

        $koordinator->delete();
    }

    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => 'database/migrations/2026_08_05_105952_create_rsm_users_table.php']);
    }

    public function test_koordinator_can_choose_staff_unit_in_own_regional(): void
    {
        $this->migrate();

        $koordinator = $this->makeUser(900061, 'Koorwil R6', RsmUser::ROLE_KOORDINATOR, 'Regional 6');
        $staff = $this->makeUser(900062, 'Staff Unit R6', RsmUser::ROLE_STAFF, 'Regional 6');

        $this->actingAs($koordinator)
            ->post(route('impersonation.store'), ['user_id' => $staff->id])
            ->assertRedirect();

        $this->assertAuthenticatedAs($staff);
        $this->assertSame($koordinator->id, session('impersonation.original_id'));

        $staff->delete();
        $koordinator->delete();
    }

    public function test_koordinator_cannot_choose_user_outside_own_subordinates(): void
    {
        $this->migrate();

        $koordinator = $this->makeUser(900063, 'Koorwil R6 Guard', RsmUser::ROLE_KOORDINATOR, 'Regional 6');
        $otherRegionalStaff = $this->makeUser(900064, 'Staff Unit R7', RsmUser::ROLE_STAFF, 'Regional 7');
        $otherKoordinator = $this->makeUser(900065, 'Koorwil R6 Other', RsmUser::ROLE_KOORDINATOR, 'Regional 6');

        $this->actingAs($koordinator)
            ->post(route('impersonation.store'), ['user_id' => $otherRegionalStaff->id])
            ->assertForbidden();
        $this->actingAs($koordinator)
            ->post(route('impersonation.store'), ['user_id' => $otherKoordinator->id])
            ->assertForbidden();

        $this->assertAuthenticatedAs($koordinator);

        $otherKoordinator->delete();
        $otherRegionalStaff->delete();
        $koordinator->delete();
    }

    public function test_super_user_can_choose_active_employee_from_another_area(): void
    {
        $this->migrate();

        $superUser = $this->makeUser(900066, 'Global Super User', RsmUser::ROLE_SUPER_USER, 'Regional 7');
        $regionalAStaff = $this->makeUser(900067, 'Staff Regional A', RsmUser::ROLE_STAFF, 'Regional 1', 'Regional A');

        $this->actingAs($superUser)
            ->post(route('impersonation.store'), ['user_id' => $regionalAStaff->id])
            ->assertRedirect();

        $this->assertAuthenticatedAs($regionalAStaff);
        $this->assertSame($superUser->id, session('impersonation.original_id'));
        $layout = new \App\View\Components\Layouts\App('Test');
        $this->assertTrue($layout->impersonationUsers->contains('id', $regionalAStaff->id));
        $this->assertTrue($layout->impersonationUsers->contains('id', $superUser->id));

        $regionalAStaff->delete();
        $superUser->delete();
    }

    public function test_non_super_impersonator_remains_limited_to_own_area(): void
    {
        $this->migrate();

        $director = $this->makeUser(900068, 'Director Regional B', RsmUser::ROLE_DIRECTOR, 'Regional 7');
        $regionalAStaff = $this->makeUser(900070, 'Other Area Staff', RsmUser::ROLE_STAFF, 'Regional 1', 'Regional A');

        $this->actingAs($director)
            ->post(route('impersonation.store'), ['user_id' => $regionalAStaff->id])
            ->assertStatus(422);

        $this->assertAuthenticatedAs($director);

        $regionalAStaff->delete();
        $director->delete();
    }

    private function makeUser(int $id, string $name, string $role, string $regional, string $area = 'Regional B'): RsmUser
    {
        return RsmUser::create([
            'id' => $id,
            'name' => $name,
            'username' => 'impersonation_'.$id,
            'password_hash' => 'x',
            'role' => $role,
            'jabatan' => $role === RsmUser::ROLE_STAFF ? 'Staff Unit' : 'Koordinator Wilayah',
            'area' => $area,
            'regional' => $regional,
            'is_active' => true,
        ]);
    }
}
