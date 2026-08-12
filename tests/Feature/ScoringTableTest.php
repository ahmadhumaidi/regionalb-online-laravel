<?php

namespace Tests\Feature;

use App\Models\RsmAdLead;
use App\Models\RsmMonthlyTarget;
use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScoringTableTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_12_094000_add_cpm_fields_to_rsm_reports_table.php',
            'database/migrations/2026_08_09_120000_add_insight_attachment_path_to_rsm_reports_table.php',
            'database/migrations/2026_08_05_105956_create_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_05_105959_create_rsm_ad_leads_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
            'database/migrations/2026_08_05_110009_create_rsm_collab_daily_metrics_table.php',
            'database/migrations/2026_08_05_110000_create_rsm_monthly_targets_table.php',
            'database/migrations/2026_08_11_100003_add_indicator_targets_to_rsm_monthly_targets_table.php',
        ]]);
    }

    public function test_staff_cannot_open_scoring_menu(): void
    {
        $this->migrate();

        $staff = RsmUser::create([
            'id' => 900032, 'name' => 'Test Staff', 'username' => 'test_staff_900032',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        $this->actingAs($staff)->get(route('scoring'))->assertForbidden();

        $staff->delete();
    }

    public function test_senior_sees_every_staff_in_roster_even_without_reports(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900033, 'name' => 'Test Senior', 'username' => 'test_senior_900033',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $activeStaff = RsmUser::create([
            'id' => 900035, 'name' => 'Scoring Staff', 'username' => 'test_scoring_staff_900035',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
        // No reports at all this period - must still appear in the table,
        // not silently disappear like it used to before the roster fix.
        $idleStaff = RsmUser::create([
            'id' => 900036, 'name' => 'Idle Staff', 'username' => 'test_idle_staff_900036',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 4', 'campus_name' => 'Kampus Lain', 'is_active' => true,
        ]);

        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Scoring Staff', 'created_by_role' => 'staff',
            'status' => 'Disetujui', 'title' => 'Scoring Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Scoring Campaign',
            'budget_requested' => 200000, 'realization_amount' => 200000,
        ]);
        for ($i = 0; $i < 3; $i++) {
            RsmAdLead::create(['report_id' => $report->id, 'lead_name' => "Lead {$i}", 'follow_up_result' => 'Sudah dihubungi', 'closing_status' => 'Registrasi']);
        }

        $response = $this->actingAs($senior)->get(route('scoring').'?date_from='.now()->toDateString().'&date_to='.now()->addDay()->toDateString());

        $response->assertOk();
        $response->assertSee('Scoring Staff');
        $response->assertSee('Regional 6');
        $response->assertSee('STIESIA Surabaya');
        // The idle staff member has zero reports for the period but is
        // still a roster row.
        $response->assertSee('Idle Staff');
        $response->assertSee('Regional 4');

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $activeStaff->delete();
        $idleStaff->delete();
        $senior->delete();
    }

    public function test_koordinator_scoring_table_scoped_to_own_wilayah(): void
    {
        $this->migrate();

        $koordinator = RsmUser::create([
            'id' => 900034, 'name' => 'Korwil Scoring', 'username' => 'test_korwil_900034',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);
        $ownStaff = RsmUser::create([
            'id' => 900037, 'name' => 'Own Wilayah Staff', 'username' => 'test_own_staff_900037',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
        $otherStaff = RsmUser::create([
            'id' => 900038, 'name' => 'Other Wilayah Staff', 'username' => 'test_other_staff_900038',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 4', 'campus_name' => 'Other Campus', 'is_active' => true,
        ]);

        $response = $this->actingAs($koordinator)->get(route('scoring').'?date_from='.now()->toDateString().'&date_to='.now()->addDay()->toDateString());

        $response->assertOk();
        $response->assertSee('Own Wilayah Staff');
        $response->assertDontSee('Other Wilayah Staff');

        $ownStaff->delete();
        $otherStaff->delete();
        $koordinator->delete();
    }

    public function test_inactive_staff_excluded_from_roster(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900039, 'name' => 'Test Senior', 'username' => 'test_senior_900039',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $inactiveStaff = RsmUser::create([
            'id' => 900040, 'name' => 'Nonaktif Staff', 'username' => 'test_inactive_staff_900040',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => false,
        ]);

        $response = $this->actingAs($senior)->get(route('scoring'));

        $response->assertOk();
        $response->assertDontSee('Nonaktif Staff');

        $inactiveStaff->delete();
        $senior->delete();
    }

    public function test_registrasi_and_herreg_columns_come_from_collab_source_not_ad_leads(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900041, 'name' => 'Test Senior', 'username' => 'test_senior_900041',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $staff = RsmUser::create([
            'id' => 900042, 'name' => 'Collab Staff', 'username' => 'test_collab_staff_900042',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        // Deliberately no ad_leads rows for this staff - if the columns
        // were still reading GamificationService's ad_leads-derived
        // fallback, they'd show 0 instead of the Collab-sourced values.
        \App\Models\RsmCollabDailyMetric::create([
            'report_name' => 'Closing Personal Per Regional', 'metric_date' => now(),
            'entity_key' => 'collab-staff-1', 'staff_name' => 'Collab Staff', 'regional' => 'Regional 6', 'value' => 42,
        ]);
        \App\Models\RsmCollabDailyMetric::create([
            'report_name' => 'Herreg Personal Per Regional', 'metric_date' => now(),
            'entity_key' => 'collab-staff-1', 'staff_name' => 'Collab Staff', 'regional' => 'Regional 6', 'value' => 17,
        ]);
        \App\Models\RsmCollabDailyMetric::create([
            'report_name' => 'Closing Kampus Regional', 'metric_date' => now(),
            'entity_key' => 'collab-campus-1', 'campus_name' => 'STIESIA Surabaya', 'regional' => 'Regional 6', 'value' => 99,
        ]);

        $table = \App\Services\Dashboard\ScoringTableService::build(
            'Regional B',
            [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
                'wilayah' => '',
                'unit_name' => '',
                'staff_name' => '',
            ],
            $senior->fresh()
        );

        $row = collect($table['rows'])->firstWhere('name', 'Collab Staff');

        $this->assertNotNull($row);
        $this->assertSame(42.0, $row['registrasi_personal']);
        $this->assertSame(17.0, $row['herregistrasi_personal']);
        $this->assertSame(99.0, $row['registrasi_kampus']);
        $this->assertSame(0.0, $row['herregistrasi_kampus']);

        $staff->delete();
        $senior->delete();
    }

    public function test_total_score_uses_monthly_indicator_target_and_weight(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900043, 'name' => 'Test Senior Score', 'username' => 'test_senior_score_900043',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $staff = RsmUser::create([
            'id' => 900044, 'name' => 'Weighted Staff', 'username' => 'test_weighted_staff_900044',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        \App\Models\RsmCollabDailyMetric::create([
            'report_name' => 'Closing Personal Per Regional', 'metric_date' => now(),
            'entity_key' => 'weighted-staff-1', 'staff_name' => 'Weighted Staff', 'regional' => 'Regional 6', 'value' => 5,
        ]);

        RsmMonthlyTarget::create([
            'area' => 'Regional B',
            'target_month' => now()->format('Y-m'),
            'scope_type' => 'staff',
            'scope_key' => 'staff:weighted staff',
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'Weighted Staff',
            'indicator_targets' => [
                'reg' => ['target' => 10, 'weight' => 20],
                'herreg' => ['target' => 4, 'weight' => 10],
            ],
        ]);

        $table = \App\Services\Dashboard\ScoringTableService::build(
            'Regional B',
            [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
                'wilayah' => '',
                'unit_name' => '',
                'staff_name' => '',
            ],
            $senior->fresh()
        );

        $row = collect($table['rows'])->firstWhere('name', 'Weighted Staff');

        $this->assertNotNull($row);
        $this->assertSame(10.0, $row['total_score']);
        $this->assertSame(30.0, $row['total_weight']);
        $this->assertSame(10.0, $row['score_details']['reg']['score']);

        $staff->delete();
        $senior->delete();
    }

    public function test_zero_ad_targets_count_as_achieved(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900052, 'name' => 'Test Senior No Cap', 'username' => 'test_senior_no_cap_900052',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $staff = RsmUser::create([
            'id' => 900053, 'name' => 'No Cap Staff', 'username' => 'test_no_cap_staff_900053',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        RsmMonthlyTarget::create([
            'area' => 'Regional B',
            'target_month' => now()->format('Y-m'),
            'scope_type' => 'staff',
            'scope_key' => 'staff:no cap staff',
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'No Cap Staff',
            'indicator_targets' => [
                'cpm' => ['target' => 0, 'weight' => 4],
                'cpl' => ['target' => 0, 'weight' => 5],
                'closing_iklan' => ['target' => 0, 'weight' => 6],
            ],
        ]);

        $table = \App\Services\Dashboard\ScoringTableService::build(
            'Regional B',
            [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
                'wilayah' => '',
                'unit_name' => '',
                'staff_name' => '',
            ],
            $senior->fresh()
        );

        $row = collect($table['rows'])->firstWhere('name', 'No Cap Staff');

        $this->assertNotNull($row);
        $this->assertSame(4.0, $row['score_details']['cpm']['score']);
        $this->assertSame(5.0, $row['score_details']['cpl']['score']);
        $this->assertSame(6.0, $row['score_details']['closing_iklan']['score']);
        $this->assertSame(15.0, $row['total_score']);
        $this->assertSame(15.0, $row['total_weight']);

        $staff->delete();
        $senior->delete();
    }

    public function test_cpm_and_cpl_use_lower_is_better_scoring(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900054, 'name' => 'Test Senior CPM', 'username' => 'test_senior_cpm_900054',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $staff = RsmUser::create([
            'id' => 900055, 'name' => 'CPM Staff', 'username' => 'test_cpm_staff_900055',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'CPM Staff', 'created_by_role' => 'staff',
            'status' => 'Diverifikasi', 'title' => 'CPM Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'CPM Campaign',
            'budget_requested' => 10000, 'realization_amount' => 8000, 'impressions_count' => 1000, 'leads_count' => 2, 'closing_count' => 1,
        ]);

        RsmMonthlyTarget::create([
            'area' => 'Regional B',
            'target_month' => now()->format('Y-m'),
            'scope_type' => 'staff',
            'scope_key' => 'staff:cpm staff',
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'CPM Staff',
            'indicator_targets' => [
                'cpm' => ['target' => 4000, 'weight' => 10],
                'cpl' => ['target' => 2000, 'weight' => 10],
                'closing_iklan' => ['target' => 2, 'weight' => 10],
            ],
        ]);

        $table = \App\Services\Dashboard\ScoringTableService::build(
            'Regional B',
            [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
                'wilayah' => '',
                'unit_name' => '',
                'staff_name' => '',
            ],
            $senior->fresh()
        );

        $row = collect($table['rows'])->firstWhere('name', 'CPM Staff');

        $this->assertNotNull($row);
        $this->assertSame(8000.0, $row['cpm']);
        $this->assertSame(4000.0, $row['cpl_iklan']);
        $this->assertSame(5.0, $row['score_details']['cpm']['score']);
        $this->assertSame(5.0, $row['score_details']['cpl']['score']);
        $this->assertSame(5.0, $row['score_details']['closing_iklan']['score']);
        $this->assertSame(15.0, $row['total_score']);

        RsmReport::where('staff_name', 'CPM Staff')->delete();
        $staff->delete();
        $senior->delete();
    }

    public function test_arena_performa_leaderboard_uses_total_score(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900045, 'name' => 'Test Senior Arena', 'username' => 'test_senior_arena_900045',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);
        $staff = RsmUser::create([
            'id' => 900046, 'name' => 'Arena Staff', 'username' => 'test_arena_staff_900046',
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);

        \App\Models\RsmCollabDailyMetric::create([
            'report_name' => 'Closing Personal Per Regional', 'metric_date' => now(),
            'entity_key' => 'arena-staff-1', 'staff_name' => 'Arena Staff', 'regional' => 'Regional 6', 'value' => 5,
        ]);

        RsmMonthlyTarget::create([
            'area' => 'Regional B',
            'target_month' => now()->format('Y-m'),
            'scope_type' => 'staff',
            'scope_key' => 'staff:arena staff',
            'wilayah' => 'Regional 6',
            'unit_name' => 'STIESIA Surabaya',
            'staff_name' => 'Arena Staff',
            'indicator_targets' => [
                'reg' => ['target' => 10, 'weight' => 20],
            ],
        ]);

        $arena = \App\Services\Dashboard\GamificationService::build(
            'Regional B',
            [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
                'wilayah' => '',
                'unit_name' => '',
                'staff_name' => '',
            ],
            $senior->fresh()
        );

        $this->assertSame('Arena Staff', $arena['leaderboard'][0]['name']);
        $this->assertSame(10.0, $arena['leaderboard'][0]['points']);
        $this->assertSame(10.0, $arena['leaderboard'][0]['total_score']);

        $staff->delete();
        $senior->delete();
    }
}
