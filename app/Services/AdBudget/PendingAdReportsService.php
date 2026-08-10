<?php

namespace App\Services\AdBudget;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\ReportScope;
use App\Services\Reports\ReportFormService;

/**
 * Ports rsm_ads_pending_reports() (rsm_db.php:3850-3872): ad reports still
 * needing staff action, for the "Belum Dilaporkan" / "Belum Tuntas
 * Dilaporkan" panel. Not period-scoped in legacy — pending items should
 * surface regardless of which ad_period the viewer currently has selected.
 */
class PendingAdReportsService
{
    private const MAX_ROWS = 300;

    /** @return array{belum_dilaporkan: list<array>, belum_tuntas: list<array>} */
    public static function build(string $area, RsmUser $user): array
    {
        $reports = ReportScope::apply(
            RsmReport::query()
                ->where('area', $area)
                ->where('report_type', RsmReport::TYPE_ADS),
            $user
        )->orderByDesc('report_date')->limit(self::MAX_ROWS)->get();

        $belumDilaporkan = [];
        $belumTuntas = [];

        foreach ($reports as $report) {
            $status = mb_strtolower(trim((string) $report->status));

            if (! in_array($status, ['dilaporkan unit', 'ditolak'], true)) {
                $belumDilaporkan[] = self::rowShape($report, $user);

                continue;
            }

            if ($status === 'dilaporkan unit' && ((float) $report->realization_amount <= 0 || blank($report->attachment_path))) {
                $belumTuntas[] = self::rowShape($report, $user);
            }
        }

        return ['belum_dilaporkan' => $belumDilaporkan, 'belum_tuntas' => $belumTuntas];
    }

    /** Matches the columns render_ads_pending_report_panel() actually shows (dashboard.php:2279-2321). */
    private static function rowShape(RsmReport $report, RsmUser $user): array
    {
        return [
            'id' => $report->id,
            'report_date' => optional($report->report_date)->format('d M Y') ?? '-',
            'ad_period' => $report->ad_period,
            'platform' => $report->platform,
            'campaign_name' => $report->campaign_name ?: $report->title,
            'budget_approved' => (float) ($report->budget_approved ?: $report->budget_requested),
            'realization_amount' => (float) $report->realization_amount,
            'has_attachment' => filled($report->attachment_path),
            'status' => $report->status,
            // Not every "belum dilaporkan" status is actually editable yet
            // (e.g. "Diverifikasi" is still awaiting final approval) - only
            // show a report action when ReportFormService::canEdit() agrees.
            'can_edit' => ReportFormService::canEdit($report, $user),
        ];
    }
}
