<?php

namespace Tests\Feature;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\DashboardOverviewService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * "Ranking Top 10 Unit/Kampus" (Dashboard) - see
 * DashboardOverviewService::ranking(). Reports with no specific campus
 * selected fall back to unit_name = the area itself, which used to show
 * the area ranked alongside real units/kampus.
 */
class DashboardRankingTest extends TestCase
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
        ]]);
    }

    /** A report with no real campus selected (unit_name defaults to the area name) must not appear in the ranking as if it were a unit/kampus. */
    public function test_area_level_reports_are_excluded_from_the_ranking(): void
    {
        $this->migrate();
        $senior = RsmUser::create([
            'id' => 960001, 'name' => 'Ranking Senior', 'username' => 'test_ranking_960001',
            'password_hash' => 'x', 'role' => 'senior', 'jabatan' => 'Senior Manager',
            'area' => 'Regional B', 'is_active' => true,
        ]);

        $areaLevel = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'Regional B', 'staff_name' => 'Some Staff', 'created_by_role' => 'staff',
            'status' => 'Dikirim', 'title' => 'Area-level activity', 'leads_count' => 50, 'closing_count' => 10,
        ]);
        $campus = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'Universitas Test', 'staff_name' => 'Some Staff', 'created_by_role' => 'staff',
            'status' => 'Dikirim', 'title' => 'Campus activity', 'leads_count' => 5, 'closing_count' => 1,
        ]);

        $filters = [
            'date_from' => now()->startOfMonth()->toDateString(), 'date_to' => now()->endOfMonth()->toDateString(),
            'wilayah' => '', 'unit_name' => '', 'staff_name' => '',
        ];
        $result = DashboardOverviewService::build('Regional B', $filters, $senior);

        $labels = array_column($result['ranking'], 'unit_label');
        $this->assertNotContains('Regional B', $labels);
        $this->assertContains('Universitas Test', $labels);

        $areaLevel->delete();
        $campus->delete();
        $senior->delete();
    }
}
