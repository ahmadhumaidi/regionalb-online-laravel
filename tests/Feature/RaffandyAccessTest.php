<?php

namespace Tests\Feature;

use App\Models\RsmUser;
use App\Support\Menu;
use App\Support\RsmRole;
use Tests\TestCase;

class RaffandyAccessTest extends TestCase
{
    public function test_executive_director_raffandy_has_super_user_menus_and_permissions(): void
    {
        $raffandy = new RsmUser(['username' => 'raffandy', 'role' => 'executive_director', 'area' => 'Regional A']);
        $super = new RsmUser(['username' => 'admin', 'role' => 'super_user', 'area' => 'Regional B']);

        $this->assertSame('executive_director', $raffandy->role);
        $this->assertSame(['Regional A', 'Regional B'], $raffandy->accessibleAreas());
        $this->assertSame(Menu::sections($super), Menu::sections($raffandy));
        foreach (array_keys(Menu::titles()) as $key) {
            $this->assertSame(Menu::isAllowed($key, $super), Menu::isAllowed($key, $raffandy), $key);
        }
        foreach ((new \ReflectionClass(RsmRole::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->name, 'can') && $method->getNumberOfParameters() === 1) {
                $this->assertSame($method->invoke(null, $super), $method->invoke(null, $raffandy), $method->name);
            }
        }
        $this->assertFalse($raffandy->isDirty('role') && $raffandy->role === 'super_user');
    }

    public function test_other_executive_directors_and_demoted_raffandy_do_not_gain_super_access(): void
    {
        foreach ([['someone', 'executive_director'], ['raffandy', 'staff']] as [$username, $role]) {
            $user = new RsmUser(['username' => $username, 'role' => $role, 'area' => 'Regional A']);
            $this->assertFalse($user->hasSuperUserAccess());
            $this->assertSame($role === 'executive_director', Menu::isAllowed('about-application', $user));
            $this->assertFalse(RsmRole::canModerateForum($user));
            $this->assertSame($role === 'executive_director' ? ['Regional A', 'Regional B'] : ['Regional A'], $user->accessibleAreas());
        }
        $this->assertFalse(RsmRole::canModerateForum(null));
    }
}
