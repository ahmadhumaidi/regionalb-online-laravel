<?php

namespace Tests\Feature;

use App\Models\RsmNotification;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Reports\ReportListService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StaffObstacleSelfHandlingTest extends TestCase
{
    public function test_staff_can_handle_own_obstacle_then_complete_or_escalate_it_to_their_coordinator(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_05_105959_create_rsm_ad_leads_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
            'database/migrations/2026_08_11_090000_add_escalated_to_role_to_rsm_reports_table.php',
            'database/migrations/2026_08_11_090002_add_leader_follow_up_text_to_rsm_reports_table.php',
            'database/migrations/2026_08_11_090001_create_rsm_notifications_table.php',
            'database/migrations/2026_08_13_090000_create_rsm_gamification_transactions_table.php',
        ]]);

        $owner = RsmUser::create(['name' => 'Staff Kendala Owner', 'username' => 'staff_kendala_owner', 'password_hash' => 'x', 'role' => RsmUser::ROLE_STAFF, 'jabatan' => 'Staff Unit', 'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'Kampus Kendala', 'is_active' => true]);
        $other = RsmUser::create(['name' => 'Staff Kendala Other', 'username' => 'staff_kendala_other', 'password_hash' => 'x', 'role' => RsmUser::ROLE_STAFF, 'jabatan' => 'Staff Unit', 'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'Kampus Kendala', 'is_active' => true]);
        $coordinator = RsmUser::create(['name' => 'Korwil Kendala', 'username' => 'korwil_kendala', 'password_hash' => 'x', 'role' => RsmUser::ROLE_KOORDINATOR, 'jabatan' => 'Koordinator Wilayah', 'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true]);

        $payload = [
            'report_date' => '2026-10-06', 'title' => 'Kendala Ditangani Sendiri', 'category' => 'Lainnya',
            'obstacle_text' => 'Kendala operasional unit', 'kendala' => '1', 'eskalasi_ke' => RsmUser::ROLE_STAFF,
        ];
        $this->actingAs($owner)->post(route('aktivitas.store'), $payload)->assertRedirect(route('aktivitas'));
        $selfHandled = RsmReport::where('title', $payload['title'])->firstOrFail();

        $this->assertSame(RsmUser::ROLE_STAFF, $selfHandled->escalated_to_role);
        $this->assertTrue(ReportListService::shape($selfHandled, $owner)['can_mark_selesai']);
        $this->assertFalse(ReportListService::shape($selfHandled, $other)['can_mark_selesai']);
        $this->actingAs($other)->post(route('reports.selesai-kendala', $selfHandled))->assertForbidden();
        $this->actingAs($owner)->post(route('reports.selesai-kendala', $selfHandled))->assertRedirect();
        $this->assertSame('Selesai', $selfHandled->fresh()->status);

        $escalated = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => '2026-10-06',
            'user_id' => $owner->id, 'wilayah' => 'Regional 6', 'unit_name' => 'Kampus Kendala',
            'staff_name' => $owner->name, 'created_by_name' => $owner->name, 'created_by_role' => RsmUser::ROLE_STAFF,
            'status' => 'Dikirim', 'escalated_to_role' => RsmUser::ROLE_STAFF, 'title' => 'Kendala Eskalasi Korwil',
            'obstacle_text' => 'Perlu bantuan korwil',
        ]);
        $this->actingAs($owner)->post(route('reports.tindak-lanjut', $escalated), ['eskalasi_ke' => RsmUser::ROLE_KOORDINATOR])->assertRedirect();

        $this->assertNull($escalated->fresh()->escalated_to_role);
        $this->assertTrue(RsmNotification::where('report_id', $escalated->id)->where('recipient_user_id', $coordinator->id)->exists());
    }
}
