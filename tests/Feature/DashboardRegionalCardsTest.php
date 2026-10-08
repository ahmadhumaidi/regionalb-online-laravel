<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\RsmUser;
use Tests\TestCase;

class DashboardRegionalCardsTest extends TestCase
{
    public function test_raffandy_sees_all_seven_cards_regardless_of_selected_area(): void
    {
        $user = new RsmUser(['username' => 'raffandy', 'role' => 'super_user', 'area' => 'Regional A']);
        $method = new \ReflectionMethod(DashboardController::class, 'recapRegionals');
        foreach (['Regional A', 'Regional B'] as $area) {
            $user->area = $area;
            $this->assertSame(
                ['Regional 1', 'Regional 2', 'Regional 3', 'Regional 4', 'Regional 5', 'Regional 6', 'Regional 7'],
                $method->invoke(new DashboardController, $area, $user),
            );
        }
    }

    public function test_single_area_and_staff_cards_remain_scoped(): void
    {
        $method = new \ReflectionMethod(DashboardController::class, 'recapRegionals');
        $user = new RsmUser(['username' => 'senior', 'role' => 'senior', 'area' => 'Regional B']);
        $this->assertSame(['Regional 4', 'Regional 5', 'Regional 6', 'Regional 7'], $method->invoke(new DashboardController, 'Regional B', $user));
        $user->role = 'staff';
        $user->regional = 'Regional 5';
        $this->assertSame(['Regional 5'], $method->invoke(new DashboardController, 'Regional B', $user));
    }
}
