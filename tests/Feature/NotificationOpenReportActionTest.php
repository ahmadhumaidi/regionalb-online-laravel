<?php

namespace Tests\Feature;

use App\Models\RsmNotification;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Models\RsmActivityLog;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NotificationOpenReportActionTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
            'database/migrations/2026_08_11_090000_add_escalated_to_role_to_rsm_reports_table.php',
            'database/migrations/2026_08_11_090002_add_leader_follow_up_text_to_rsm_reports_table.php',
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

    public function test_aktivitas_follow_up_action_uses_modal_instead_of_inline_row_form(): void
    {
        $this->migrate();

        $koordinator = RsmUser::create([
            'id' => 920002,
            'name' => 'Korwil Modal Test',
            'username' => 'korwil_modal_test',
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
            'title' => 'Kendala modal test',
            'obstacle_text' => 'Butuh modal tindak lanjut',
        ]);

        $response = $this->actingAs($koordinator)->get(route('aktivitas'));

        $response->assertOk();
        $response->assertSee('Tindak Lanjuti');
        $response->assertSee('Tindak Lanjuti Kendala');
        $response->assertSee('Perlu tindakan');
        $response->assertSee('Kendala perlu ditangani');
        $response->assertSee('background-color:#fffbeb', false);
        $response->assertSee('fixed inset-0 z-50', false);
        $response->assertDontSee('<details', false);

        $report->delete();
        $koordinator->delete();
    }

    public function test_aktivitas_summary_cards_filter_the_matching_obstacle_workflow(): void
    {
        $this->migrate();
        $compiledViews = sys_get_temp_dir().'/rsm-report-views-'.uniqid();
        mkdir($compiledViews, 0777, true);
        config(['view.compiled' => $compiledViews]);

        $viewer = RsmUser::create([
            'id' => 920004,
            'name' => 'Summary Filter Admin',
            'username' => 'summary_filter_admin',
            'password_hash' => 'x',
            'role' => RsmUser::ROLE_SUPER_USER,
            'jabatan' => 'Super User',
            'area' => 'Regional B',
            'is_active' => true,
        ]);
        $base = [
            'area' => 'Regional B',
            'report_type' => RsmReport::TYPE_OTHER,
            'report_date' => now(),
            'wilayah' => 'Regional 6',
            'unit_name' => 'Unit Filter',
            'staff_name' => 'Staff Filter',
            'created_by_name' => 'Staff Filter',
            'created_by_role' => RsmUser::ROLE_STAFF,
            'obstacle_text' => 'Kendala filter card',
        ];
        $korwil = RsmReport::create($base + ['status' => 'Dikirim', 'title' => 'Kendala Khusus Korwil']);
        $senior = RsmReport::create($base + ['status' => 'Ditindak Lanjuti', 'title' => 'Kendala Khusus Senior']);
        $senior->forceFill(['escalated_to_role' => RsmUser::ROLE_SENIOR])->save();
        $done = RsmReport::create($base + ['status' => 'Selesai', 'title' => 'Kendala Sudah Selesai']);
        $approved = RsmReport::create($base + ['status' => 'Disetujui', 'title' => 'Kendala Final Disetujui']);

        $this->actingAs($viewer)->get(route('aktivitas', ['type' => 'kendala', 'workflow' => 'active']))
            ->assertOk()->assertSee($korwil->title)->assertSee($senior->title)->assertDontSee($done->title)->assertDontSee($approved->title);
        $this->get(route('aktivitas', ['type' => 'kendala', 'workflow' => 'korwil']))
            ->assertOk()->assertSee($korwil->title)->assertDontSee($senior->title)->assertDontSee($done->title)->assertDontSee($approved->title);
        $this->get(route('aktivitas', ['type' => 'kendala', 'workflow' => 'senior']))
            ->assertOk()->assertSee($senior->title)->assertDontSee($korwil->title)->assertDontSee($done->title);
        $this->get(route('aktivitas', ['type' => 'kendala', 'workflow' => 'done']))
            ->assertOk()->assertSee($done->title)->assertDontSee($korwil->title)->assertDontSee($senior->title);

        $korwil->delete();
        $senior->delete();
        $done->delete();
        $approved->delete();
        $viewer->delete();
    }

    public function test_staff_report_detail_separates_leader_follow_up_from_staff_follow_up(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 920003,
            'name' => 'Ilham Khusaini',
            'username' => 'ilham_staff_test',
            'password_hash' => 'x',
            'role' => RsmUser::ROLE_STAFF,
            'jabatan' => 'Staff Unit',
            'regional' => 'Regional 5',
            'campus_name' => 'UBY',
            'area' => 'Regional B',
            'is_active' => true,
        ]);
        $report = RsmReport::create([
            'area' => 'Regional B',
            'report_type' => RsmReport::TYPE_OTHER,
            'report_date' => now(),
            'user_id' => $staff->id,
            'wilayah' => 'Regional 5',
            'unit_name' => 'UBY',
            'staff_name' => 'Ilham Khusaini',
            'created_by_name' => 'Ilham Khusaini',
            'created_by_role' => RsmUser::ROLE_STAFF,
            'status' => 'Ditindak Lanjuti',
            'title' => 'Rapat Konsolidasi',
            'obstacle_text' => 'minimnya data',
            'follow_up_text' => 'gunakan iklan meta ads dengan tujuan CTWA dan Prospek',
        ]);
        $log = RsmActivityLog::create([
            'report_id' => $report->id,
            'area' => 'Regional B',
            'actor_user_id' => 1,
            'actor_role' => RsmUser::ROLE_SUPER_USER,
            'actor_name' => 'Ahmad Humaidi',
            'action_name' => 'tindak_lanjut',
            'old_status' => 'Dikirim',
            'new_status' => 'Ditindak Lanjuti',
            'note' => 'gunakan iklan meta ads dengan tujuan CTWA dan Prospek',
        ]);

        $response = $this->actingAs($staff)->get(route('reports.show', $report));

        $response->assertOk();
        $response->assertSee('Tindak lanjut staff');
        $response->assertSee('Tindak lanjut dari pimpinan');
        $response->assertSee('gunakan iklan meta ads dengan tujuan CTWA dan Prospek');
        $response->assertDontSee('Tindak lanjut staff</dt><dd class="mt-1 text-ink">gunakan iklan meta ads', false);

        $log->delete();
        $report->delete();
        $staff->delete();
    }
}
