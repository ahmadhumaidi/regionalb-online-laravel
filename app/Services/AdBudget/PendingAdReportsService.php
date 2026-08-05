<?php

namespace App\Services\AdBudget;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\ReportScope;

/**
 * Ports rsm_ads_pending_reports() (rsm_db.php:3850-3872): ad reports still
 * needing staff action, for the "Belum Dilaporkan" / "Belum Tuntas
 * Dilaporkan" panel. Not period-scoped in legacy — pending items should
 * surface regardless of which ad_period the viewer currently has selected.
 */
class PendingAdReportsService
{
    /** @return array{belum_dilaporkan: list<array>, belum_tuntas: list<array>} */
    public static function build(string $area, RsmUser $user): array
    {
        $reports = ReportScope::apply(
            RsmReport::query()
                ->where('area', $area)
                ->where('report_type', RsmReport::TYPE_ADS),
            $user
        )->orderByDesc('report_date')->get();

        $belumDilaporkan = [];
        $belumTuntas = [];

        foreach ($reports as $report) {
            $status = mb_strtolower(trim((string) $report->status));

            if (! in_array($status, ['dilaporkan unit', 'ditolak'], true)) {
                $belumDilaporkan[] = self::rowShape($report);

                continue;
            }

            if ($status === 'dilaporkan unit' && ((float) $report->realization_amount <= 0 || blank($report->attachment_path))) {
                $belumTuntas[] = self::rowShape($report);
            }
        }

        return ['belum_dilaporkan' => $belumDilaporkan, 'belum_tuntas' => $belumTuntas];
    }

    private static function rowShape(RsmReport $report): array
    {
        return [
            'id' => $report->id,
            'report_date' => optional($report->report_date)->format('d M Y') ?? '-',
            'ad_period' => $report->ad_period,
            'wilayah' => $report->wilayah,
            'unit_name' => $report->unit_name,
            'campaign_name' => $report->campaign_name,
            'status' => $report->status,
        ];
    }
}
