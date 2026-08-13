<?php

namespace Tests\Feature;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\GamificationService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * "Skor Performa" (Profile page's Aura card) - see
 * GamificationService::recentPerformanceScore(). Deliberately stays a live
 * 30-day rolling calculation, not part of the XP ledger, unlike League/XP.
 */
class AuraScoreTest extends TestCase
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

    private function makeStaff(int $id, string $name): RsmUser
    {
        return RsmUser::create([
            'id' => $id, 'name' => $name, 'username' => 'test_aura_'.$id,
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
    }

    /** A report older than the 30-day window must not count, even if its volume dwarfs the recent one - the score reflects current activity, not lifetime history. */
    public function test_staff_score_only_counts_the_last_30_days(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(950001, 'Recent Window Staff');

        $recent = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Recent Window Staff', 'created_by_role' => 'staff',
            'status' => 'Dikirim', 'title' => 'Laporan baru', 'leads_count' => 2, 'closing_count' => 1,
        ]);
        $old = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => now()->subDays(40),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Recent Window Staff', 'created_by_role' => 'staff',
            'status' => 'Dikirim', 'title' => 'Laporan lama', 'leads_count' => 100, 'closing_count' => 100,
        ]);

        // Only the recent report counts: reports(1)*4 + leads(2)*2 + closing(1)*8 = 16.
        $this->assertSame(16, GamificationService::recentPerformanceScore($staff));

        $recent->delete();
        $old->delete();
        $staff->delete();
    }

    /** High-volume recent activity still caps at 100, same as before. */
    public function test_staff_score_caps_at_100(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(950002, 'Cap Staff');

        $reports = [];
        for ($i = 0; $i < 30; $i++) {
            $reports[] = RsmReport::create([
                'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => now(),
                'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Cap Staff', 'created_by_role' => 'staff',
                'status' => 'Dikirim', 'title' => "Laporan {$i}",
            ]);
        }

        $this->assertSame(100, GamificationService::recentPerformanceScore($staff));

        foreach ($reports as $report) {
            $report->delete();
        }
        $staff->delete();
    }

    /**
     * Koordinator's score is their team's recent pooled volume averaged by
     * active staff headcount, not the raw pooled total - same principle
     * averageTeamProfileXp() already applies to XP, so wilayah size alone
     * doesn't inflate/deflate a koordinator's Aura score.
     */
    public function test_koordinator_score_is_team_average_within_recent_window(): void
    {
        $this->migrate();
        $koordinator = RsmUser::create([
            'id' => 950003, 'name' => 'Aura Koordinator', 'username' => 'test_aura_koor_950003',
            'password_hash' => 'x', 'role' => 'koordinator', 'jabatan' => 'Koordinator Wilayah',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'is_active' => true,
        ]);

        $staffAccounts = [];
        $reports = [];
        for ($i = 0; $i < 10; $i++) {
            $name = "Aura Team Staff {$i}";
            $staffAccounts[] = RsmUser::create([
                'id' => 950100 + $i, 'name' => $name, 'username' => 'test_aura_team_'.(950100 + $i),
                'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
                'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
            ]);
            $reports[] = RsmReport::create([
                'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => now(),
                'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => $name, 'created_by_role' => 'staff',
                'status' => 'Dikirim', 'title' => "Laporan {$name}", 'leads_count' => 5, 'closing_count' => 2,
            ]);
        }

        // Pooled: reports(10) leads(50) closing(20). Averaged by 10 staff:
        // reports(1)*4 + leads(5)*2 + closing(2)*8 = 4 + 10 + 16 = 30.
        $this->assertSame(30, GamificationService::recentPerformanceScore($koordinator));

        foreach ($reports as $report) {
            $report->delete();
        }
        foreach ($staffAccounts as $staffAccount) {
            $staffAccount->delete();
        }
        $koordinator->delete();
    }
}
