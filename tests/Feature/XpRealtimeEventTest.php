<?php

namespace Tests\Feature;

use App\Models\RsmAdLead;
use App\Models\RsmGamificationTransaction;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\XpService;
use App\Services\Reports\ReportFormService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Gamification Refactor Phase 2: real-time XP at the authoritative source
 * (XpService::syncReportEventXp()), instead of waiting for
 * XpService::syncPersonalActivity()'s daily reconciliation to notice the
 * change next time the staff visits their Profile page. See
 * ReportFormService::create()/update(), ReportStatusController,
 * AdBudgetActionController, ObstacleFollowUpController, and
 * AdLeadImportService for the call sites.
 */
class XpRealtimeEventTest extends TestCase
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
            'database/migrations/2026_08_13_090000_create_rsm_gamification_transactions_table.php',
        ]]);
    }

    private function makeStaff(int $id, string $name): RsmUser
    {
        return RsmUser::create([
            'id' => $id, 'name' => $name, 'username' => 'test_rt_'.$id,
            'password_hash' => 'x', 'role' => 'staff', 'jabatan' => 'Staff Unit',
            'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'STIESIA Surabaya', 'is_active' => true,
        ]);
    }

    /** ReportFormService::create() is the real controller-level entry point for every report type - confirms XP lands the moment the report exists, not on a later Profile visit. */
    public function test_report_form_service_create_awards_xp_immediately(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(930001, 'Realtime Create Staff');

        $report = ReportFormService::create(RsmReport::TYPE_OTHER, [
            'report_date' => now()->toDateString(),
            'title' => 'Rapat koordinasi',
            'category' => 'Meeting internal',
        ], null, $staff);

        $this->assertSame(5, XpService::getLifetimeXp($staff));
        $this->assertDatabaseHas('rsm_gamification_transactions', [
            'user_id' => $staff->id, 'event_type' => 'report_created', 'source_type' => 'report', 'source_id' => $report->id, 'xp' => 5,
        ]);

        $report->delete();
        $staff->delete();
    }

    /** Calling syncReportEventXp() twice on the same report must not duplicate any event. */
    public function test_sync_report_event_xp_is_idempotent(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(930002, 'Idempotent Report Staff');
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Idempotent Report Staff', 'created_by_role' => 'staff',
            'status' => 'Diverifikasi', 'title' => 'Idempotent Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'Idempotent Campaign',
            'budget_requested' => 100000, 'realization_amount' => 100000,
        ]);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Lead 1', 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar', 'notes' => 'Lengkap']);

        XpService::syncReportEventXp($report->fresh());
        $xpAfterFirst = XpService::getLifetimeXp($staff);
        $txCountAfterFirst = RsmGamificationTransaction::where('user_id', $staff->id)->count();

        XpService::syncReportEventXp($report->fresh());
        XpService::syncReportEventXp($report->fresh());

        $this->assertSame($xpAfterFirst, XpService::getLifetimeXp($staff));
        $this->assertSame($txCountAfterFirst, RsmGamificationTransaction::where('user_id', $staff->id)->count());
        $this->assertGreaterThan(0, $xpAfterFirst);

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    /** A report moving through several approved statuses (Diverifikasi -> Selesai) must only earn the report_approved bonus once, not per transition. */
    public function test_report_approved_event_fires_only_once_across_multiple_transitions(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(930003, 'Multi Transition Staff');
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_MARKETING, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Multi Transition Staff', 'created_by_role' => 'staff',
            'status' => 'Dikirim', 'title' => 'Kunjungan sekolah',
        ]);

        XpService::syncReportEventXp($report->fresh()); // status still Dikirim - no report_approved yet
        $this->assertSame(0, RsmGamificationTransaction::where('user_id', $staff->id)->where('event_type', 'report_approved')->count());

        $report->update(['status' => 'Diverifikasi']);
        XpService::syncReportEventXp($report->fresh());
        $report->update(['status' => 'Selesai']);
        XpService::syncReportEventXp($report->fresh());

        $this->assertSame(1, RsmGamificationTransaction::where('user_id', $staff->id)->where('event_type', 'report_approved')->count());
        $this->assertSame(15, XpService::getLifetimeXp($staff)); // report_created(5) + report_approved(10), once each

        $report->delete();
        $staff->delete();
    }

    /**
     * The core double-counting guard: once real-time events have banked a
     * staff member's report/lead XP, syncPersonalActivity()'s daily
     * reconciliation (which now only covers Collab-sourced registrasi/
     * herreg for staff) must NOT add anything more for that same activity,
     * since there's no Collab data seeded in this fixture.
     */
    public function test_activity_sync_does_not_double_count_realtime_report_xp(): void
    {
        $this->migrate();
        $staff = $this->makeStaff(930004, 'No Double Count Staff');
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_ADS, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'No Double Count Staff', 'created_by_role' => 'staff',
            'status' => 'Diverifikasi', 'title' => 'No Dup Campaign', 'platform' => 'Meta Ads', 'campaign_name' => 'No Dup Campaign',
            'budget_requested' => 100000, 'realization_amount' => 100000,
        ]);
        RsmAdLead::create(['report_id' => $report->id, 'lead_name' => 'Lead 1', 'closing_status' => 'Registrasi', 'follow_up_result' => 'Sudah daftar', 'notes' => 'Lengkap']);

        XpService::syncReportEventXp($report->fresh());
        $xpAfterRealtime = XpService::getLifetimeXp($staff);
        $this->assertGreaterThan(0, $xpAfterRealtime);

        // No Collab data seeded, so the sync's live total (profileSyncXp,
        // Collab-only for staff) is 0 - must never subtract, and must not
        // re-add what real-time already banked.
        XpService::syncPersonalActivity($staff);

        $this->assertSame($xpAfterRealtime, XpService::getLifetimeXp($staff));
        $this->assertSame(
            0,
            RsmGamificationTransaction::where('user_id', $staff->id)->where('event_type', 'activity_sync')->count()
        );

        RsmAdLead::where('report_id', $report->id)->delete();
        $report->delete();
        $staff->delete();
    }

    /** A report/lead with no resolvable staff account (unknown staff_name) is safely skipped, not an error. */
    public function test_unresolvable_staff_author_is_skipped_safely(): void
    {
        $this->migrate();
        $report = RsmReport::create([
            'area' => 'Regional B', 'report_type' => RsmReport::TYPE_OTHER, 'report_date' => now(),
            'wilayah' => 'Regional 6', 'unit_name' => 'STIESIA Surabaya', 'staff_name' => 'Nobody Registered', 'created_by_role' => 'staff',
            'status' => 'Dikirim', 'title' => 'Orphan report',
        ]);

        XpService::syncReportEventXp($report->fresh());

        $this->assertSame(0, RsmGamificationTransaction::count());

        $report->delete();
    }
}
