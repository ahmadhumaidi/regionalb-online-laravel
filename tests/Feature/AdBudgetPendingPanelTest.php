<?php

namespace Tests\Feature;

use App\Models\RsmAdLead;
use App\Models\RsmAdBudgetLimit;
use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdBudgetPendingPanelTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_05_105956_create_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_12_093000_add_unit_name_to_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_05_105959_create_rsm_ad_leads_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
            'database/migrations/2026_08_13_090000_create_rsm_gamification_transactions_table.php',
        ]]);
    }

    public function test_koordinator_can_set_campus_budget_limit(): void
    {
        $this->migrate();

        DB::table('partner_campuses')->insert([
            'name' => 'STIESIA Surabaya',
            'display_name' => 'STIESIA Surabaya',
            'kode_kampus' => 'STIESIA-T',
            'address' => '-',
        ]);
        $koordinator = RsmUser::create([
            'id' => 900050, 'name' => 'Korwil Budget', 'username' => 'test_korwil_budget_900050',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);
        RsmAdBudgetLimit::create([
            'area' => 'Regional B', 'ad_period' => 'Agustus 2026', 'wilayah' => 'Regional 6',
            'unit_name' => '', 'budget_limit' => 1000000,
            'created_by_user_id' => $koordinator->id, 'created_by_name' => 'Super User',
        ]);

        $response = $this->actingAs($koordinator)->post(route('anggaran.limit.store'), [
            'ad_period' => \App\Services\AdBudget\AdBudgetPeriods::default('2026-08-01'),
            'wilayah' => 'Regional 4',
            'unit_name' => 'STIESIA Surabaya',
            'budget_limit' => 750000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rsm_ad_budget_limits', [
            'area' => 'Regional B',
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'budget_limit' => 750000,
        ]);

        RsmAdBudgetLimit::where('unit_name', 'STIESIA Surabaya')->delete();
        DB::table('partner_campuses')->where('kode_kampus', 'STIESIA-T')->delete();
        $koordinator->delete();
    }

    public function test_campus_allocation_immediately_creates_approved_staff_report(): void
    {
        $this->migrate();

        $campusId = DB::table('partner_campuses')->insertGetId([
            'name' => 'Universitas Allocation',
            'display_name' => 'Campus Allocation',
            'kode_kampus' => 'AUTO-ALLOC-T',
            'address' => '-',
        ]);
        $koordinator = RsmUser::create([
            'id' => 900057, 'name' => 'Korwil Auto Allocation', 'username' => 'test_korwil_auto_900057',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);
        $staff = RsmUser::create([
            'id' => 900058, 'name' => 'Staff Auto Allocation', 'username' => 'test_staff_auto_900058',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'Campus Allocation', 'is_active' => true,
        ]);
        RsmAdBudgetLimit::create([
            'area' => 'Regional B', 'ad_period' => 'Agustus 2026', 'wilayah' => 'Regional 6',
            'unit_name' => '', 'budget_limit' => 1000000,
            'created_by_user_id' => 1, 'created_by_name' => 'Super User',
        ]);

        $this->actingAs($koordinator)->post(route('anggaran.limit.store'), [
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'Campus Allocation',
            'budget_limit' => 750000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rsm_reports', [
            'report_type' => RsmReport::TYPE_ADS,
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'Campus Allocation',
            'partner_campus_id' => $campusId,
            'user_id' => $staff->id,
            'staff_name' => $staff->name,
            'status' => 'Disetujui',
            'budget_requested' => 750000,
            'budget_approved' => 750000,
        ]);

        $this->actingAs($koordinator)->post(route('anggaran.limit.store'), [
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'Campus Allocation',
            'budget_limit' => 250000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rsm_ad_budget_limits', [
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'Campus Allocation',
            'budget_limit' => 1000000,
        ]);
        $campusReports = RsmReport::where('user_id', $staff->id)->where('ad_period', 'Agustus 2026')->get();
        $this->assertCount(2, $campusReports);
        $this->assertSame(1000000.0, (float) $campusReports->sum('budget_requested'));

        $report = RsmReport::where('user_id', $staff->id)->where('ad_period', 'Agustus 2026')->firstOrFail();
        $this->actingAs($staff)->get('/anggaran?ad_period=Agustus%202026')
            ->assertOk()
            ->assertSee('Laporkan')
            ->assertSee(route('reports.edit', $report));

        RsmReport::where('user_id', $staff->id)->where('ad_period', 'Agustus 2026')->delete();
        RsmAdBudgetLimit::where('wilayah', 'Regional 6')->delete();
        $staff->delete();
        $koordinator->delete();
        DB::table('partner_campuses')->where('id', $campusId)->delete();
    }

    public function test_super_user_sets_regional_pool_without_selecting_campus(): void
    {
        $this->migrate();

        $superUser = RsmUser::create([
            'id' => 900052, 'name' => 'Super Regional Budget', 'username' => 'test_super_budget_900052',
            'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true,
        ]);

        $this->actingAs($superUser)->get('/anggaran?ad_period=Agustus%202026')
            ->assertOk()
            ->assertSee('<option value="Regional B"', false);

        $this->actingAs($superUser)->post(route('anggaran.limit.store'), [
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'budget_limit' => 2500000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rsm_ad_budget_limits', [
            'area' => 'Regional B', 'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6', 'unit_name' => '', 'budget_limit' => 2500000,
        ]);

        RsmAdBudgetLimit::where('created_by_user_id', $superUser->id)->delete();
        $superUser->delete();
    }

    public function test_only_super_user_can_delete_unit_allocation_without_deleting_pool_or_reports(): void
    {
        $this->migrate();

        $superUser = RsmUser::create([
            'id' => 900060, 'name' => 'Super Delete Budget', 'username' => 'test_super_delete_budget_900060',
            'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $koordinator = RsmUser::create([
            'id' => 900061, 'name' => 'Korwil Cannot Delete', 'username' => 'test_korwil_cannot_delete_900061',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);
        foreach (['' => 1000000, 'Campus Delete Test' => 750000] as $unitName => $amount) {
            RsmAdBudgetLimit::create([
                'area' => 'Regional B', 'ad_period' => 'Agustus 2026', 'wilayah' => 'Regional 6',
                'unit_name' => $unitName, 'budget_limit' => $amount,
                'created_by_user_id' => $superUser->id, 'created_by_name' => $superUser->name,
            ]);
        }
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'Campus Delete Test', 'status' => 'Disetujui',
            'title' => 'Historical Campaign', 'platform' => 'Meta Ads', 'ad_period' => 'Agustus 2026',
            'campaign_name' => 'Historical Campaign', 'budget_requested' => 750000, 'budget_approved' => 750000,
        ]);

        $payload = [
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'Campus Delete Test',
        ];
        $this->actingAs($koordinator)->delete(route('anggaran.limit.destroy'), $payload)->assertForbidden();
        $this->assertSame(2, RsmAdBudgetLimit::query()
            ->where('created_by_user_id', $superUser->id)
            ->count());

        $this->actingAs($superUser)->delete(route('anggaran.limit.destroy'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('rsm_ad_budget_limits', [
            'area' => 'Regional B', 'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6', 'unit_name' => 'Campus Delete Test',
        ]);
        $this->assertDatabaseHas('rsm_ad_budget_limits', [
            'area' => 'Regional B', 'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6', 'unit_name' => '', 'budget_limit' => 1000000,
        ]);
        $this->assertDatabaseHas('rsm_reports', ['id' => $report->id, 'title' => 'Historical Campaign']);

        $report->delete();
        RsmAdBudgetLimit::where('created_by_user_id', $superUser->id)->delete();
        $koordinator->delete();
        $superUser->delete();
    }

    public function test_koordinator_can_allocate_and_report_own_regional_ad(): void
    {
        $this->migrate();

        $owner = RsmUser::create([
            'id' => 900054, 'name' => 'Kundi Harto', 'username' => 'test_kundi_harto_900054',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 5', 'is_active' => true,
        ]);
        $otherCoordinator = RsmUser::create([
            'id' => 900055, 'name' => 'Korwil Lain R5', 'username' => 'test_korwil_lain_900055',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 5', 'is_active' => true,
        ]);
        RsmAdBudgetLimit::create([
            'area' => 'Regional B', 'ad_period' => 'Agustus 2026', 'wilayah' => 'Regional 5',
            'unit_name' => '', 'budget_limit' => 2000000,
            'created_by_user_id' => $owner->id, 'created_by_name' => 'Super User',
        ]);

        $this->actingAs($owner)->get('/anggaran?ad_period=Agustus%202026')
            ->assertOk()
            ->assertSee('Iklan Regional 5');

        $this->actingAs($owner)->post(route('anggaran.limit.store'), [
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 4',
            'unit_name' => 'Iklan Regional 5',
            'budget_limit' => 1000000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $automaticReport = RsmReport::where('unit_name', 'Iklan Regional 5')
            ->where('ad_period', 'Agustus 2026')
            ->where('created_by_name', $owner->name)
            ->firstOrFail();
        $this->assertSame('Disetujui', $automaticReport->status);
        $this->assertSame(1000000.0, (float) $automaticReport->budget_approved);

        $this->actingAs($owner)->get('/anggaran?ad_period=Agustus%202026')
            ->assertOk()
            ->assertSee('Anggaran Iklan Regional 5 - Agustus 2026')
            ->assertSee(route('reports.edit', $automaticReport));

        $this->actingAs($owner)->post(route('anggaran.store'), [
            'report_date' => '2026-08-15',
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 4',
            'unit_name' => 'Iklan Regional 5',
            'platform' => 'Meta Ads',
            'campaign_name' => 'Campaign Regional Lima',
            'ad_goal' => 'Leads',
            'budget_requested' => 500000,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $report = RsmReport::where('campaign_name', 'Campaign Regional Lima')->firstOrFail();
        $this->assertSame('Regional 5', $report->wilayah);
        $this->assertSame('Kundi Harto', $report->created_by_name);
        $report->update(['status' => 'Disetujui', 'budget_approved' => 500000]);

        $this->actingAs($owner)->patch(route('reports.update', $report), [
            'report_date' => '2026-08-15',
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 5',
            'unit_name' => 'Iklan Regional 5',
            'platform' => 'Meta Ads',
            'campaign_name' => 'Campaign Regional Lima',
            'ad_goal' => 'Leads',
            'budget_requested' => 500000,
            'realization_amount' => 450000,
            'impressions_count' => 9000,
            'campaign_link' => 'https://example.test/campaign-regional-5',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $report->refresh();
        $this->assertSame('Dilaporkan Unit', $report->status);
        $this->assertSame(450000.0, (float) $report->realization_amount);
        $this->assertSame(9000, (int) $report->impressions_count);
        $this->assertSame('Kundi Harto', $report->staff_name);

        $this->actingAs($otherCoordinator)->get(route('reports.show', $report))->assertNotFound();
        $this->actingAs($otherCoordinator)->post(route('anggaran.verifikasi', $report))->assertForbidden();
        $this->actingAs($owner)->post(route('anggaran.verifikasi', $report))->assertForbidden();

        $report->delete();
        $automaticReport->delete();
        RsmAdBudgetLimit::where('wilayah', 'Regional 5')->delete();
        $owner->delete();
        $otherCoordinator->delete();
    }

    public function test_koordinator_cannot_use_another_regional_ad_unit(): void
    {
        $this->migrate();

        $koordinator = RsmUser::create([
            'id' => 900056, 'name' => 'Korwil Regional Six', 'username' => 'test_korwil_r6_900056',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);

        $this->actingAs($koordinator)->post(route('anggaran.store'), [
            'report_date' => '2026-08-15',
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 5',
            'unit_name' => 'Iklan Regional 5',
            'platform' => 'Meta Ads',
            'campaign_name' => 'Campaign Regional Salah',
            'ad_goal' => 'Leads',
            'budget_requested' => 500000,
        ])->assertSessionHasErrors('unit_name');

        $this->assertDatabaseMissing('rsm_reports', ['campaign_name' => 'Campaign Regional Salah']);
        $koordinator->delete();
    }

    public function test_koordinator_cannot_allocate_more_than_regional_pool(): void
    {
        $this->migrate();

        DB::table('partner_campuses')->insert([
            'name' => 'Campus Allocation Test', 'display_name' => 'Campus Allocation Test',
            'kode_kampus' => 'ALLOC-T', 'address' => '-',
        ]);
        $koordinator = RsmUser::create([
            'id' => 900053, 'name' => 'Korwil Allocation', 'username' => 'test_korwil_alloc_900053',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);
        RsmAdBudgetLimit::create([
            'area' => 'Regional B', 'ad_period' => 'Agustus 2026', 'wilayah' => 'Regional 6',
            'unit_name' => '', 'budget_limit' => 500000,
            'created_by_user_id' => $koordinator->id, 'created_by_name' => 'Super User',
        ]);

        $this->actingAs($koordinator)->post(route('anggaran.limit.store'), [
            'ad_period' => 'Agustus 2026', 'wilayah' => 'Regional 6',
            'unit_name' => 'Campus Allocation Test', 'budget_limit' => 600000,
        ])->assertSessionHasErrors('budget_limit');

        $this->assertDatabaseMissing('rsm_ad_budget_limits', ['unit_name' => 'Campus Allocation Test']);

        RsmAdBudgetLimit::where('wilayah', 'Regional 6')->delete();
        DB::table('partner_campuses')->where('kode_kampus', 'ALLOC-T')->delete();
        $koordinator->delete();
    }

    public function test_ads_request_uses_campus_budget_limit_not_regional_pool(): void
    {
        $this->migrate();

        foreach (['STIESIA Surabaya', 'Other Campus'] as $index => $campus) {
            DB::table('partner_campuses')->insert([
                'name' => $campus,
                'display_name' => $campus,
                'kode_kampus' => 'CAMPUS-LIMIT-'.$index,
                'address' => '-',
            ]);
        }
        $koordinator = RsmUser::create([
            'id' => 900051, 'name' => 'Korwil Request', 'username' => 'test_korwil_request_900051',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);
        RsmAdBudgetLimit::create([
            'area' => 'Regional B',
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'budget_limit' => 500000,
            'created_by_user_id' => $koordinator->id,
            'created_by_name' => $koordinator->name,
        ]);

        $this->actingAs($koordinator)->post(route('anggaran.store'), [
            'report_date' => '2026-08-12',
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'Other Campus',
            'platform' => 'Meta Ads',
            'campaign_name' => 'Other Campus Campaign',
            'ad_goal' => 'Leads',
            'budget_requested' => 100000,
        ])->assertSessionHasErrors('budget_requested');

        $this->actingAs($koordinator)->post(route('anggaran.store'), [
            'report_date' => '2026-08-12',
            'ad_period' => 'Agustus 2026',
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'platform' => 'Meta Ads',
            'campaign_name' => 'Allowed Campus Campaign',
            'ad_goal' => 'Leads',
            'budget_requested' => 100000,
        ])->assertRedirect();

        $this->assertDatabaseHas('rsm_reports', [
            'campaign_name' => 'Allowed Campus Campaign',
            'unit_name' => 'STIESIA Surabaya',
            'budget_requested' => 100000,
        ]);

        RsmReport::whereIn('campaign_name', ['Allowed Campus Campaign', 'Other Campus Campaign'])->delete();
        RsmAdBudgetLimit::where('wilayah', 'Regional 6')->delete();
        DB::table('partner_campuses')->whereIn('kode_kampus', ['CAMPUS-LIMIT-0', 'CAMPUS-LIMIT-1'])->delete();
        $koordinator->delete();
    }

    public function test_anggaran_page_shows_report_link_only_when_editable(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 900013, 'name' => 'Test Staff', 'username' => 'test_staff_900013',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
        $otherStaff = RsmUser::create([
            'id' => 900059, 'name' => 'Other Campus Staff', 'username' => 'test_other_campus_staff_900059',
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
        $otherStaffReport = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'user_id' => $otherStaff->id, 'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya',
            'staff_name' => $otherStaff->name, 'created_by_role' => 'koordinator', 'status' => 'Disetujui',
            'title' => 'Other Staff Private Campaign', 'platform' => 'Meta Ads',
            'campaign_name' => 'Other Staff Private Campaign', 'budget_requested' => 75000,
        ]);
        RsmAdBudgetLimit::create([
            'area' => 'Regional B', 'ad_period' => \App\Services\AdBudget\AdBudgetPeriods::default(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'budget_limit' => 100000,
            'created_by_user_id' => 1, 'created_by_name' => 'Test Koordinator',
        ]);
        RsmAdBudgetLimit::create([
            'area' => 'Regional B', 'ad_period' => \App\Services\AdBudget\AdBudgetPeriods::default(),
            'wilayah' => 'Regional 6', 'unit_name' => 'Other Campus', 'budget_limit' => 75000,
            'created_by_user_id' => 1, 'created_by_name' => 'Test Koordinator',
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
        $response->assertDontSee('Other Staff Private Campaign');
        $response->assertDontSee('Other Campus');
        $this->actingAs($staff)->get(route('reports.show', $otherStaffReport))->assertNotFound();

        $otherStaffReport->delete();
        RsmAdBudgetLimit::where('wilayah', 'Regional 6')->delete();
        $approved->delete();
        $verifying->delete();
        $draft->delete();
        $otherStaff->delete();
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

    public function test_ad_goal_metric_uses_only_the_relevant_result(): void
    {
        $report = new RsmReport([
            'realization_amount' => 600000,
            'impressions_count' => 12000,
            'leads_count' => 6,
            'closing_count' => 2,
            'cpl' => 100000,
        ]);

        $report->ad_goal = 'Leads';
        $this->assertSame(['label' => 'CPL', 'value' => 100000.0, 'money' => true], $report->adGoalMetric());

        $report->ad_goal = 'Awareness';
        $this->assertSame(['label' => 'Impresi', 'value' => 12000.0, 'money' => false], $report->adGoalMetric());

        $report->ad_goal = 'Traffic';
        $this->assertSame(['label' => 'CPT', 'value' => 100000.0, 'money' => true], $report->adGoalMetric());

        $report->ad_goal = 'Conversion';
        $this->assertSame(['label' => 'CPR', 'value' => 300000.0, 'money' => true], $report->adGoalMetric());
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
            'status' => 'Diverifikasi', 'title' => 'Complete Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Complete Campaign',
            'budget_requested' => 100000,
        ]);

        // A koordinator - outside the canManageAdBudget() tier - is not
        // allowed to mark a report "Selesai", even once it's verified.
        $this->actingAs($koordinator)->post(route('anggaran.selesai', $report))->assertForbidden();
        $this->assertSame('Diverifikasi', $report->fresh()->status);

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

    public function test_selesai_requires_diverifikasi_status_first(): void
    {
        $this->migrate();

        $superUser = RsmUser::create([
            'id' => 900028, 'name' => 'Test Super', 'username' => 'test_super_900028',
            'password_hash' => 'x', 'role' => 'super_user', 'jabatan' => 'Super User',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        // Reported but not yet verified by korwil - "Tandai Selesai" must
        // not be reachable yet, even for super_user/senior.
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Dilaporkan Unit', 'title' => 'Unverified Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Unverified Campaign',
            'budget_requested' => 100000,
        ]);

        $this->actingAs($superUser)->post(route('anggaran.selesai', $report))->assertStatus(422);
        $this->assertSame('Dilaporkan Unit', $report->fresh()->status);

        $report->delete();
        $superUser->delete();
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

        // Not verified yet - senior ticking the checkbox must not force
        // "Selesai" before korwil/senior has verified the reported evidence.
        $this->actingAs($senior)->patch(route('reports.update', $report), [
            'report_date' => now()->toDateString(),
            'ad_period' => $report->ad_period,
            'platform' => 'Meta Ads',
            'budget_requested' => 100000,
            'mark_selesai' => '1',
        ])->assertRedirect();
        $this->assertSame('Dilaporkan Unit', $report->fresh()->status);

        // Once verified, senior manager ticking the checkbox on the edit
        // form should mark the report "Selesai" in the same request,
        // without a separate trip to the list page's dedicated button.
        $report->update(['status' => 'Diverifikasi']);
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

    public function test_koordinator_and_senior_can_verify_own_ads_reported_evidence(): void
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
        // Already Disetujui, then staff/korwil reported evidence -
        // "Dilaporkan Unit" - so it's now waiting on korwil's review.
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Dilaporkan Unit', 'title' => 'Verify Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Verify Campaign',
            'budget_requested' => 300000, 'budget_approved' => 300000,
        ]);

        // Can't verify while still just "Pengajuan"/"Disetujui" - only once
        // the evidence has actually been reported.
        $notYetReported = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Disetujui', 'title' => 'Not Reported Yet', 'platform' => 'Meta Ads', 'campaign_name' => 'Not Reported Yet',
            'budget_requested' => 100000,
        ]);
        $this->actingAs($koordinatorR6)->post(route('anggaran.verifikasi', $notYetReported))->assertStatus(422);

        // A koordinator from a different wilayah can't verify this report.
        $this->actingAs($koordinatorR4)->post(route('anggaran.verifikasi', $report))->assertForbidden();
        $this->assertSame('Dilaporkan Unit', $report->fresh()->status);

        // The koordinator who owns Regional 6 can.
        $this->actingAs($koordinatorR6)->post(route('anggaran.verifikasi', $report))->assertRedirect();
        $this->assertSame('Diverifikasi', $report->fresh()->status);

        // Once verified, senior manager can now mark it "Selesai".
        $this->actingAs($senior)->post(route('anggaran.selesai', $report))->assertRedirect();
        $this->assertSame('Selesai', $report->fresh()->status);

        $notYetReported->delete();
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
            'status' => 'Dilaporkan Unit', 'title' => 'Verify Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Verify Campaign',
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

    public function test_normalize_legacy_ads_statuses_command_migrates_draft_and_transfer_invoice(): void
    {
        $this->migrate();

        $draftReport = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 4', 'unit_name' => 'Legacy Campus', 'staff_name' => 'Legacy Staff', 'created_by_role' => 'staff',
            'status' => 'Draft', 'title' => 'Legacy Draft Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Legacy Draft Campaign',
            'budget_requested' => 500000,
        ]);
        $transferReport = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 5', 'unit_name' => 'Legacy Campus 2', 'staff_name' => 'Legacy Staff', 'created_by_role' => 'staff',
            'status' => 'Transfer / Invoice', 'title' => 'Legacy Transfer Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Legacy Transfer Campaign',
            'budget_requested' => 200000,
        ]);
        // Untouched - not one of the retired statuses.
        $unrelatedReport = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Test Staff', 'created_by_role' => 'staff',
            'status' => 'Pengajuan', 'title' => 'Current Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Current Campaign',
            'budget_requested' => 100000,
        ]);

        $this->artisan('rsm:normalize-legacy-ads-statuses', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('Draft', $draftReport->fresh()->status);
        $this->assertSame('Transfer / Invoice', $transferReport->fresh()->status);

        $this->artisan('rsm:normalize-legacy-ads-statuses')->assertSuccessful();
        $this->assertSame('Disetujui', $draftReport->fresh()->status);
        $this->assertSame('Dilaporkan Unit', $transferReport->fresh()->status);
        $this->assertSame('Pengajuan', $unrelatedReport->fresh()->status);

        $this->assertDatabaseHas('rsm_activity_logs', [
            'report_id' => $draftReport->id,
            'old_status' => 'Draft',
            'new_status' => 'Disetujui',
            'actor_role' => 'system',
        ]);

        $draftReport->delete();
        $transferReport->delete();
        $unrelatedReport->delete();
    }
}
