<?php

namespace Tests\Feature;

use App\Models\RsmAdLead;
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
            'database/migrations/2026_08_09_120000_add_insight_attachment_path_to_rsm_reports_table.php',
            'database/migrations/2026_08_05_105956_create_rsm_ad_budget_limits_table.php',
            'database/migrations/2026_08_05_105959_create_rsm_ad_leads_table.php',
            'database/migrations/2026_08_05_110006_create_rsm_activity_logs_table.php',
            'database/migrations/2026_08_05_110009_create_rsm_collab_daily_metrics_table.php',
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

    public function test_senior_sees_every_staff_indicator_row_on_scoring_table(): void
    {
        $this->migrate();

        $senior = RsmUser::create([
            'id' => 900033, 'name' => 'Test Senior', 'username' => 'test_senior_900033',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
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

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
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
        $ownReport = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Own Wilayah Staff', 'created_by_role' => 'staff',
            'status' => 'Disetujui', 'title' => 'Own Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Own Campaign',
            'budget_requested' => 100000,
        ]);
        $otherReport = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 4', 'unit_name' => 'Other Campus', 'staff_name' => 'Other Wilayah Staff', 'created_by_role' => 'staff',
            'status' => 'Disetujui', 'title' => 'Other Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Other Campaign',
            'budget_requested' => 100000,
        ]);

        $response = $this->actingAs($koordinator)->get(route('scoring').'?date_from='.now()->toDateString().'&date_to='.now()->addDay()->toDateString());

        $response->assertOk();
        $response->assertSee('Own Wilayah Staff');
        $response->assertDontSee('Other Wilayah Staff');

        $ownReport->delete();
        $otherReport->delete();
        $koordinator->delete();
    }
}
