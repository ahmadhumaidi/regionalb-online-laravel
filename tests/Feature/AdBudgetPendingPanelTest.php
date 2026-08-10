<?php

namespace Tests\Feature;

use App\Models\RsmAdLead;
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
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
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
        $response->assertSee('Laporkan');
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

    public function test_cpl_is_auto_computed_from_realization_and_lead_count(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 900016, 'name' => 'Test Staff', 'username' => 'test_staff_900016',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff', 'status' => 'Disetujui',
            'title' => 'CPL Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'CPL Campaign', 'budget_requested' => 500000,
            'leads_count' => 5,
        ]);
        for ($i = 0; $i < 5; $i++) {
            RsmAdLead::create(['report_id' => $report->id, 'lead_name' => "Lead {$i}"]);
        }

        $response = $this->actingAs($staff)->patch(route('reports.update', $report), [
            'campaign_name' => 'CPL Campaign',
            'realization_amount' => 500000,
        ]);

        $response->assertRedirect();
        $this->assertSame(100000.0, (float) $report->fresh()->cpl);

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    public function test_grouped_table_renders_with_status_colors_for_senior(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900017, 'name' => 'Test Senior', 'username' => 'test_senior_900017',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);

        $period = \App\Services\AdBudget\AdBudgetPeriods::default();
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Disetujui', 'title' => 'Grouped Table Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Grouped Table Campaign',
            'budget_requested' => 100000, 'ad_period' => $period,
        ]);

        $response = $this->actingAs($senior)->get('/anggaran?ad_period='.urlencode($period));

        $response->assertOk();
        $response->assertSee('Grouped Table Campaign');
        $response->assertSee('Regional: Regional 6');
        $response->assertSee('Disetujui');

        $report->delete();
        $senior->delete();
    }

    public function test_only_senior_manager_and_super_user_can_mark_ads_report_selesai(): void
    {
        $this->migrate();

        $koordinator = RsmUser::create([
            'id' => 900020, 'name' => 'Test Koordinator', 'username' => 'test_koordinator_900020',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $superUser = RsmUser::create([
            'id' => 900018, 'name' => 'Test Super', 'username' => 'test_super_900018',
            'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $senior = RsmUser::create([
            'id' => 900019, 'name' => 'Test Senior', 'username' => 'test_senior_900019',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Dilaporkan Unit', 'title' => 'Complete Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Complete Campaign',
            'budget_requested' => 100000,
        ]);

        // A koordinator - outside the canManageAdBudget() tier - is not
        // allowed to mark a report "Selesai".
        $this->actingAs($koordinator)->post(route('anggaran.selesai', $report))->assertForbidden();
        $this->assertSame('Dilaporkan Unit', $report->fresh()->status);

        // super_user is treated the same as senior for this action - Ahmad
        // Humaidi (the developer) logs in as super_user day-to-day.
        $this->actingAs($superUser)->post(route('anggaran.selesai', $report))->assertRedirect();
        $this->assertSame('Selesai', $report->fresh()->status);

        // Once already "Selesai", even super_user/senior can't re-trigger it.
        $this->actingAs($senior)->post(route('anggaran.selesai', $report))->assertStatus(422);

        $report->delete();
        $senior->delete();
        $superUser->delete();
        $koordinator->delete();
    }

    public function test_mark_selesai_checkbox_on_edit_form_transitions_status(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900021, 'name' => 'Test Senior', 'username' => 'test_senior_900021',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $staff = RsmUser::create([
            'id' => 900022, 'name' => 'Test Staff', 'username' => 'test_staff_900022',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Dilaporkan Unit', 'title' => 'Complete Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Complete Campaign',
            'budget_requested' => 100000, 'ad_period' => \App\Services\AdBudget\AdBudgetPeriods::default(),
        ]);

        // Staff submitting mark_selesai=1 on their own edit form must not
        // be able to force the transition - they aren't in canManageAdBudget().
        $this->actingAs($staff)->patch(route('reports.update', $report), [
            'campaign_name' => 'Complete Campaign',
            'mark_selesai' => '1',
        ])->assertRedirect();
        $this->assertSame('Dilaporkan Unit', $report->fresh()->status);

        // Senior manager ticking the checkbox on the edit form should mark
        // the report "Selesai" in the same request, without a separate
        // trip to the list page's dedicated action button.
        $this->actingAs($senior)->patch(route('reports.update', $report), [
            'report_date' => now()->toDateString(),
            'ad_period' => $report->ad_period,
            'platform' => 'Meta Ads',
            'budget_requested' => 100000,
            'mark_selesai' => '1',
        ])->assertRedirect();
        $this->assertSame('Selesai', $report->fresh()->status);

        $report->delete();
        $staff->delete();
        $senior->delete();
    }

    public function test_koordinator_and_senior_can_verify_own_ads_pengajuan(): void
    {
        $this->migrate();

        $koordinatorR6 = RsmUser::create([
            'id' => 900023, 'name' => 'Korwil R6', 'username' => 'test_korwil_900023',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);
        $koordinatorR4 = RsmUser::create([
            'id' => 900024, 'name' => 'Korwil R4', 'username' => 'test_korwil_900024',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 4', 'is_active' => true,
        ]);
        $senior = RsmUser::create([
            'id' => 900025, 'name' => 'Test Senior', 'username' => 'test_senior_900025',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'koordinator',
            'status' => 'Pengajuan', 'title' => 'Verify Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Verify Campaign',
            'budget_requested' => 300000,
        ]);

        // A koordinator from a different wilayah can't verify this request.
        $this->actingAs($koordinatorR4)->post(route('anggaran.verifikasi', $report))->assertForbidden();
        $this->assertSame('Pengajuan', $report->fresh()->status);

        // The koordinator who owns Regional 6 can.
        $this->actingAs($koordinatorR6)->post(route('anggaran.verifikasi', $report))->assertRedirect();
        $this->assertSame('Diverifikasi', $report->fresh()->status);

        // Once verified, Setujui/Tolak/Revisi still work for the senior tier.
        $this->actingAs($senior)->post(route('anggaran.setujui', $report), ['budget_approved' => 300000])->assertRedirect();
        $this->assertSame('Disetujui', $report->fresh()->status);

        $report->delete();
        $senior->delete();
        $koordinatorR4->delete();
        $koordinatorR6->delete();
    }

    public function test_mark_verified_checkbox_on_edit_form_transitions_status(): void
    {
        $this->migrate();

        $koordinator = RsmUser::create([
            'id' => 900026, 'name' => 'Korwil R6', 'username' => 'test_korwil_900026',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'koordinator',
            'status' => 'Pengajuan', 'title' => 'Verify Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Verify Campaign',
            'budget_requested' => 300000, 'ad_period' => \App\Services\AdBudget\AdBudgetPeriods::default(),
        ]);

        $this->actingAs($koordinator)->get(route('reports.edit', $report))
            ->assertOk()
            ->assertSee('name="mark_verified"', false);

        $this->actingAs($koordinator)->patch(route('reports.update', $report), [
            'report_date' => now()->toDateString(),
            'ad_period' => $report->ad_period,
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'platform' => 'Meta Ads',
            'budget_requested' => 300000,
            'mark_verified' => '1',
        ])->assertRedirect();
        $this->assertSame('Diverifikasi', $report->fresh()->status);

        $report->delete();
        $koordinator->delete();
    }

    public function test_uploading_invoice_on_disetujui_report_no_longer_auto_switches_to_transfer_invoice(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900027, 'name' => 'Test Senior', 'username' => 'test_senior_900027',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Disetujui', 'title' => 'Invoice Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Invoice Campaign',
            'budget_requested' => 100000, 'ad_period' => \App\Services\AdBudget\AdBudgetPeriods::default(),
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf');

        $this->actingAs($senior)->patch(route('reports.update', $report), [
            'report_date' => now()->toDateString(),
            'ad_period' => $report->ad_period,
            'platform' => 'Meta Ads',
            'budget_requested' => 100000,
            'attachment_path' => $file,
        ])->assertRedirect();

        // "Transfer / Invoice" was retired from the ads status machine -
        // uploading the invoice proof no longer moves status off "Disetujui".
        $this->assertSame('Disetujui', $report->fresh()->status);
        $this->assertNotNull($report->fresh()->attachment_path);

        $report->delete();
        $senior->delete();
    }
}
