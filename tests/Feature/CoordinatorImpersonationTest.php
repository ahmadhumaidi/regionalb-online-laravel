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

    private function makeUser(int $id, string $name, string $role, string $regional): RsmUser
    {
        return RsmUser::create([
            'id' => $id,
            'name' => $name,
            'username' => 'impersonation_'.$id,
            'password_hash' => 'x',
            'role' => $role,
            'jabatan' => $role === RsmUser::ROLE_STAFF ? 'Staff Unit' : 'Koordinator Wilayah',
            'area' => 'Regional B',
            'regional' => $regional,
            'is_active' => true,
        ]);
    }
}
