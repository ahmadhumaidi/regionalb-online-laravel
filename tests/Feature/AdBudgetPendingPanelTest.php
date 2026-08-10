<?php

namespace Tests\Feature;

use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdBudgetPendingPanelTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_05_105956_create_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_05_105959_create_rsm_ad_leads_table.php',
        ]]);
    }

    public function test_anggaran_page_shows_report_link_only_when_editable(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 900013, 'name' => 'Test Staff', 'username' => 'test_staff_900013',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        $approved = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff', 'status' => 'Disetujui',
            'title' => 'Approved Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Approved Campaign', 'budget_requested' => 100000,
        ]);
        // Staff/koordinator can report evidence even while still awaiting
        // final approval (Diverifikasi), not just after Disetujui.
        $verifying = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff', 'status' => 'Diverifikasi',
            'title' => 'Verifying Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Verifying Campaign', 'budget_requested' => 200000,
        ]);
        // A report that's only just been drafted/submitted, not even
        // through koordinator verification yet, still isn't reportable.
        $draft = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff', 'status' => 'Draft',
            'title' => 'Draft Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Draft Campaign', 'budget_requested' => 50000,
        ]);

        $response = $this->actingAs($staff)->get('/anggaran');

        $response->assertOk();
        $response->assertSee('Approved Campaign');
        $response->assertSee('Verifying Campaign');
        $response->assertSee('Draft Campaign');
        $response->assertSee('Lapor realisasi');
        $response->assertSee('Menunggu persetujuan');
        $response->assertSee(route('reports.edit', $approved));
        $response->assertSee(route('reports.edit', $verifying));

        $approved->delete();
        $verifying->delete();
        $draft->delete();
        $staff->delete();
    }

    public function test_staff_sees_invoice_upload_field_on_ads_edit_form(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 900014, 'name' => 'Test Staff', 'username' => 'test_staff_900014',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        $approved = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff', 'status' => 'Disetujui',
            'title' => 'Approved Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Approved Campaign', 'budget_requested' => 100000,
        ]);

        $response = $this->actingAs($staff)->get(route('reports.edit', $approved));

        $response->assertOk();
        $response->assertSee('name="attachment_path"', false);
        $response->assertSee('Upload bukti invoice/screenshot');

        $approved->delete();
        $staff->delete();
    }

    public function test_report_stays_in_belum_tuntas_until_all_three_uploads_present(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 900015, 'name' => 'Test Staff', 'username' => 'test_staff_900015',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        // Realization + invoice are filled in, but insight and data hasil
        // are not - this used to silently drop off "Belum Tuntas".
        $partiallyDone = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Dilaporkan Unit', 'title' => 'Partial Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Partial Campaign',
            'budget_requested' => 100000, 'realization_amount' => 95000, 'attachment_path' => 'ads/invoice.pdf',
        ]);

        $response = $this->actingAs($staff)->get('/anggaran');

        $response->assertOk();
        $response->assertSee('Belum Tuntas Dilaporkan');
        $response->assertSee('Partial Campaign');
        $response->assertSee('Lengkapi');

        $partiallyDone->delete();
        $staff->delete();
    }
}
