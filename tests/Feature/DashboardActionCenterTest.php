<?php

namespace Tests\Feature;

use App\Models\RsmCoordinatorSchedule;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\ActionCenterService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DashboardActionCenterTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_05_105959_create_rsm_ad_leads_table.php',
            'database/migrations/2026_08_05_110002_create_rsm_coordinator_schedules_table.php',
        ]]);
    }

    public function test_staff_sees_incomplete_ad_report_as_an_action(): void
    {
        $this->migrate();
        $staff = RsmUser::create([
            'name' => 'Action Staff', 'username' => 'action_staff', 'password_hash' => 'x',
            'role' => 'staff', 'jabatan' => 'Staff Unit', 'area' => 'Regional B',
            'regional' => 'Regional 6', 'campus_name' => 'Action Campus', 'is_active' => true,
        ]);
        RsmReport::create([
            'area' => 'Regional B', 'report_type' => 'ads', 'report_date' => now(),
            'user_id' => $staff->id, 'wilayah' => 'Regional 6', 'unit_name' => 'Action Campus',
            'staff_name' => $staff->name, 'created_by_role' => 'staff', 'status' => 'Disetujui',
            'title' => 'Action Campaign', 'campaign_name' => 'Action Campaign', 'budget_requested' => 100000,
        ]);

        $items = ActionCenterService::build('Regional B', $staff);

        $this->assertSame(1, collect($items)->firstWhere('label', 'Lengkapi laporan iklan')['count']);
    }

    public function test_coordinator_sees_verification_and_todays_schedule(): void
    {
        $this->migrate();
        $coordinator = RsmUser::create([
            'name' => 'Action Coordinator', 'username' => 'action_coordinator', 'password_hash' => 'x',
            'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah', 'area' => 'Regional B',
            'regional' => 'Regional 6', 'is_active' => true,
        ]);
        RsmReport::create([
            'area' => 'Regional B', 'report_type' => 'ads', 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'Action Campus', 'staff_name' => 'Action Staff',
            'created_by_role' => 'staff', 'status' => 'Dilaporkan Unit', 'title' => 'Ready to Verify',
        ]);
        RsmCoordinatorSchedule::create([
            'area' => 'Regional B', 'schedule_date' => today(), 'koordinator_user_id' => $coordinator->id,
            'koordinator_name' => $coordinator->name, 'wilayah' => 'Regional 6', 'unit_name' => 'Action Campus',
            'visit_type' => 'Visit', 'agenda' => 'Review hari ini', 'status' => 'Rencana',
        ]);

        $items = collect(ActionCenterService::build('Regional B', $coordinator));

        $this->assertSame(1, $items->firstWhere('label', 'Verifikasi laporan iklan')['count']);
        $this->assertSame(1, $items->firstWhere('label', 'Jadwal hari ini')['count']);
    }
}
