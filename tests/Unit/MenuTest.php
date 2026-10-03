<?php

namespace Tests\Unit;

use App\Models\RsmUser;
use App\Support\Menu;
use PHPUnit\Framework\TestCase;

class MenuTest extends TestCase
{
    public function test_staff_menu_prioritizes_daily_work_and_hides_management_tools(): void
    {
        $sections = Menu::sections(new RsmUser(['role' => RsmUser::ROLE_STAFF]));
        $titles = array_column($sections, 'title');
        $keys = collect($sections)->flatMap(fn (array $section) => array_column($section['items'], 'key'));

        $this->assertContains('Pekerjaan Saya', $titles);
        $this->assertContains('profile', $keys);
        $this->assertNotContains('targets', $keys);
        $this->assertNotContains('users', $keys);
        $this->assertNotContains('role', $keys);
    }

    public function test_manager_menu_exposes_planning_and_administration(): void
    {
        $sections = Menu::sections(new RsmUser(['role' => RsmUser::ROLE_SUPER_USER]));
        $keys = collect($sections)->flatMap(fn (array $section) => array_column($section['items'], 'key'));

        $this->assertContains('targets', $keys);
        $this->assertContains('closing-target-simulation', $keys);
        $this->assertContains('jadwal-personalia', $keys);
        $this->assertContains('users', $keys);
        $this->assertContains('sumber-collab', $keys);
        $this->assertContains('role', $keys);
    }
}
