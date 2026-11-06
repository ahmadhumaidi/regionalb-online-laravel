<?php

namespace Tests\Feature;

use App\Models\RsmReport;
use App\Models\RsmSeniorActivityReport;
use App\Models\RsmUser;
use App\Services\Reports\ReportFormService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ActivityMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
            'database/migrations/2026_08_11_090000_add_escalated_to_role_to_rsm_reports_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_13_090000_create_rsm_gamification_transactions_table.php',
            'database/migrations/2026_10_06_000000_create_rsm_senior_activity_reports_table.php',
            'database/migrations/2026_10_24_000000_add_activity_metrics_to_reports.php',
            'database/migrations/2026_10_25_010000_add_activity_name_to_reports.php',
        ]]);
    }

    public function test_activity_and_marketing_metrics_are_saved_and_editable(): void
    {
        $user = $this->user();
        $this->assertContains('Pelayanan Mahasiswa Lama', ReportFormService::config('other')['options']);

        foreach (['aktivitas.store' => 'other', 'kegiatan.store' => 'marketing'] as $route => $type) {
            $this->actingAs($user)->post(route($route), [
                'report_date' => '2026-10-24', 'title' => 'Pelayanan '.$type,
                'category' => 'Pelayanan Mahasiswa Lama', 'activity_name' => 'Layanan administrasi mahasiswa', 'duration_minutes' => 45, 'urgency' => 3,
            ])->assertSessionHasNoErrors()->assertRedirect();

            $report = RsmReport::where('report_type', $type)->firstOrFail();
            $this->assertSame(45, $report->duration_minutes);
            $this->assertSame('Layanan administrasi mahasiswa', $report->activity_name);
            $this->assertSame(3, $report->urgency);

            $this->actingAs($user)->patch(route('reports.update', $report), [
                'report_date' => '2026-10-24', 'title' => $report->title,
                'duration_minutes' => 60, 'urgency' => 1,
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame(60, $report->fresh()->duration_minutes);
            $this->assertSame('Layanan administrasi mahasiswa', $report->fresh()->activity_name);
            $this->assertSame(1, $report->fresh()->urgency);
        }
    }

    public function test_invalid_metrics_are_rejected(): void
    {
        $this->actingAs($this->user())->post(route('aktivitas.store'), [
            'report_date' => '2026-10-24', 'title' => 'Invalid',
            'duration_minutes' => 0, 'urgency' => 4,
        ])->assertSessionHasErrors(['duration_minutes', 'urgency']);
        $this->assertDatabaseCount('rsm_reports', 0);
    }

    public function test_unified_form_saves_marketing_categories_with_their_original_type(): void
    {
        $this->actingAs($this->user());
        $this->get(route('kegiatan.create'))->assertRedirect(route('aktivitas.create', ['activity' => 'Follow up leads']));
        $this->assertContains('Kunjungan sekolah', ReportFormService::activityOptions());
        $this->assertContains('Pelayanan Mahasiswa Lama', ReportFormService::activityOptions());

        $this->post(route('aktivitas.store'), [
            'report_date' => '2026-10-24', 'title' => 'Kunjungan sekolah mitra',
            'category' => 'Kunjungan sekolah', 'duration_minutes' => 60, 'urgency' => 2,
        ])->assertSessionHasNoErrors()->assertRedirect(route('aktivitas'));
        $this->assertDatabaseHas('rsm_reports', [
            'report_type' => 'marketing', 'activity_kind' => 'Kunjungan sekolah',
            'duration_minutes' => 60, 'urgency' => 2,
        ]);
    }

    public function test_unified_digital_activity_requires_a_link(): void
    {
        $this->actingAs($this->user());
        $payload = ['report_date' => '2026-10-24', 'title' => 'Promosi digital', 'category' => 'Upload Footage'];
        $this->post(route('aktivitas.store'), $payload)->assertSessionHasErrors('campaign_link');
        $this->post(route('aktivitas.store'), $payload + ['campaign_link' => 'https://example.com/konten'])
            ->assertSessionHasNoErrors()->assertRedirect(route('aktivitas'));
        $this->assertDatabaseHas('rsm_reports', [
            'report_type' => 'marketing', 'activity_kind' => 'Upload Footage',
            'campaign_link' => 'https://example.com/konten',
        ]);
    }

    public function test_legacy_digital_activity_is_renamed_without_changing_its_contents(): void
    {
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => 'marketing', 'report_date' => '2026-10-24',
            'wilayah' => 'Regional 6', 'unit_name' => 'Kampus Contoh', 'staff_name' => 'Staff Contoh',
            'created_by_role' => 'staff', 'title' => 'Dokumentasi kegiatan kampus',
            'activity_kind' => 'Aktivitas digital', 'category' => 'Aktivitas digital',
            'campaign_link' => 'https://example.com/footage', 'duration_minutes' => 45, 'urgency' => 2,
        ]);
        $updatedAt = $report->updated_at->toDateTimeString();
        $migration = require database_path('migrations/2026_10_25_000000_rename_digital_activity_to_upload_footage.php');
        $migration->up();
        $report->refresh();

        $this->assertSame('Upload Footage', $report->activity_kind);
        $this->assertSame('Upload Footage', $report->category);
        $this->assertSame('Dokumentasi kegiatan kampus', $report->title);
        $this->assertSame('https://example.com/footage', $report->campaign_link);
        $this->assertSame(45, $report->duration_minutes);
        $this->assertSame(2, $report->urgency);
        $this->assertSame($updatedAt, $report->updated_at->toDateTimeString());
    }

    public function test_senior_metrics_are_saved_and_invalid_values_rejected(): void
    {
        $this->actingAs($this->user());
        $payload = ['activity_date' => '2026-10-24', 'activity_type' => 'Rapat',
            'title' => 'Evaluasi', 'agenda' => 'Koordinasi', 'result_text' => 'Selesai',
            'duration_minutes' => 90, 'urgency' => 2];
        $this->post(route('laporan-senior.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $report = RsmSeniorActivityReport::firstOrFail();
        $this->assertSame(90, $report->duration_minutes);
        $this->assertSame(2, $report->urgency);
        $this->patch(route('laporan-senior.update', $report), array_replace($payload, [
            'duration_minutes' => -1, 'urgency' => 0,
        ]))->assertSessionHasErrors(['duration_minutes', 'urgency']);
        $this->assertSame(90, $report->fresh()->duration_minutes);
    }

    public function test_legacy_reports_can_keep_metrics_empty(): void
    {
        $this->actingAs($this->user())->post(route('aktivitas.store'), [
            'report_date' => '2026-10-24', 'title' => 'Tanpa durasi',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $report = RsmReport::firstOrFail();
        $this->assertNull($report->duration_minutes);
        $this->assertNull($report->urgency);
    }

    private function user(): RsmUser
    {
        return RsmUser::create(['name' => 'Metrics Admin', 'username' => 'metrics_admin',
            'password_hash' => 'x', 'role' => RsmUser::ROLE_SUPER_USER, 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true]);
    }
}
