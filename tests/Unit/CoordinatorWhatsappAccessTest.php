<?php

namespace Tests\Unit;

use App\Models\RsmUser;
use App\Support\RsmRole;
use PHPUnit\Framework\TestCase;

class CoordinatorWhatsappAccessTest extends TestCase
{
    public function test_only_senior_manager_and_above_can_access_coordinator_whatsapp_reports(): void
    {
        foreach (['super_user', 'executive_director', 'director', 'senior'] as $role) {
            $this->assertTrue(RsmRole::canViewCoordinatorWhatsappReport(new RsmUser(['role' => $role])), $role);
        }

        foreach (['mentor', 'koordinator', 'staff'] as $role) {
            $this->assertFalse(RsmRole::canViewCoordinatorWhatsappReport(new RsmUser(['role' => $role])), $role);
        }

        $this->assertFalse(RsmRole::canViewCoordinatorWhatsappReport(null));
    }
}
