<?php

namespace Tests\Feature;

use App\Models\RsmNotification;
use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationOpenReportActionTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
            'database/migrations/2026_08_11_090000_add_escalated_to_role_to_rsm_reports_table.php',
            'database/migrations/2026_08_11_090001_create_rsm_notifications_table.php',
        ]]);
    }

    public function test_koordinator_opens_kendala_notification_to_actionable_report_detail(): void
    {
        $this->migrate();

        $koordinator = RsmUser::create([
            'id' => 920001,
            'name' => 'Korwil Notif Test',
            'username' => 'korwil_notif_test',
            'password_hash' => 'x',
            'role' => RsmUser::ROLE_KOORDINATOR,
            'jabatan' => 'Koordinator Wilayah',
            'regional' => 'Regional 6',
            'area' => 'Regional B',
            'is_active' => true,
        ]);

        $report = RsmReport::create([
            'area' => 'Regional B',
            'report_type' => RsmReport::TYPE_OTHER,
            'report_date' => now(),
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'Fuad',
            'created_by_name' => 'Fuad',
            'created_by_role' => RsmUser::ROLE_STAFF,
            'status' => 'Dikirim',
            'title' => 'Kendala dari Fuad',
            'obstacle_text' => 'Butuh tindak lanjut korwil',
        ]);

        $notification = RsmNotification::create([
            'area' => 'Regional B',
            'recipient_user_id' => $koordinator->id,
            'report_id' => $report->id,
            'type' => 'kendala',
            'title' => 'Kendala baru',
            'message' => 'Fuad mengirim kendala.',
            'is_read' => false,
        ]);

        $openResponse = $this->actingAs($koordinator)->get(route('notifications.open', $notification));
        $openResponse->assertRedirect(route('reports.show', $report));
        $this->assertTrue($notification->fresh()->is_read);

        $detailResponse = $this->actingAs($koordinator)->get(route('reports.show', $report));
        $detailResponse->assertOk();
        $detailResponse->assertSee('Tindakan Kendala');
        $detailResponse->assertSee('Tindak Lanjuti');
        $detailResponse->assertSee('Selesai');

        $notification->delete();
        $report->delete();
        $koordinator->delete();
    }
}
