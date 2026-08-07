<?php

namespace App\Services\AdBudget;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\DashboardNumbers;
use App\Services\Dashboard\ReportScope;
use App\Support\RsmRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ports the "Anggaran & Laporan Iklan" table body: rsm_reports(area, 'ads',
 * limit 500, ...) plus ads_grouped_reports()/ads_group_total_cells()
 * (dashboard.php:3648-3671). Unlike the Dashboard Utama funnel, leads/
 * closing counts here are read straight off the report row's own
 * leads_count/closing_count columns, not aggregated from rsm_ad_leads —
 * matching legacy, since those columns are kept in sync elsewhere.
 */
class AdBudgetReportsService
{
    private const MAX_ROWS = 500;

    /** @return array{groups: list<array>, totals: array, total_count: int, shown_count: int} */
    public static function build(string $area, string $period, RsmUser $user): array
    {
        $scoped = ReportScope::apply(
            RsmReport::query()
                ->where('area', $area)
                ->where('report_type', RsmReport::TYPE_ADS)
                ->where('ad_period', $period),
            $user
        );

        $totalCount = (clone $scoped)->count();
        $reports = $scoped->orderByDesc('report_date')->limit(self::MAX_ROWS)->get();

        $names = DB::table('partner_campuses')->get(['id', 'name', 'display_name'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->display_name ?: $c->name]);

        $regionalGroups = $reports->groupBy('wilayah')->map(function (Collection $regionalRows, $wilayah) use ($names, $user) {
            $campusGroups = $regionalRows->groupBy(fn (RsmReport $r) => $r->partner_campus_id ? 'campus:'.$r->partner_campus_id : 'unit:'.$r->unit_name)
                ->map(function (Collection $campusRows) use ($names, $user) {
                    $first = $campusRows->first();
                    $label = $first->partner_campus_id
                        ? ($names[$first->partner_campus_id] ?? $first->unit_name)
                        : ($first->unit_name ?: '-');

                    return [
                        'label' => $label,
                        'rows' => $campusRows->map(fn (RsmReport $r) => self::rowShape($r, $user))->values()->all(),
                        'subtotal' => self::subtotal($campusRows),
                    ];
                })
                ->sortBy('label')
                ->values();

            return [
                'wilayah' => $wilayah ?: '-',
                'campuses' => $campusGroups->all(),
                'subtotal' => self::subtotal($regionalRows),
            ];
        });

        $order = \App\Support\AreaRegionals::forArea($area);
        $groups = $regionalGroups->sortBy(function ($group) use ($order) {
            $index = array_search($group['wilayah'], $order, true);

            return $index === false ? [1, $group['wilayah']] : [0, $index];
        })->values()->all();

        return [
            'groups' => $groups,
            'totals' => self::subtotal($reports),
            'total_count' => $totalCount,
            'shown_count' => $reports->count(),
        ];
    }

    private static function rowShape(RsmReport $report, RsmUser $user): array
    {
        $status = (string) $report->status;
        $statusLower = mb_strtolower(trim($status));
        $canReview = RsmRole::canReviewAdBudgetRequest($user) && in_array($status, ['Pengajuan', 'Revisi'], true);
        $canComplete = $user->role === 'koordinator'
            && $report->wilayah === $user->regional
            && $statusLower === 'dilaporkan unit';
        $canReportRealization = in_array($user->role, ['staff', 'koordinator'], true)
            && $report->wilayah === $user->regional
            && in_array($statusLower, ['disetujui', 'dilaporkan unit'], true);

        return [
            'id' => $report->id,
            'report_date' => optional($report->report_date)->format('d M Y') ?? '-',
            'ad_period' => $report->ad_period,
            'campaign_name' => $report->campaign_name,
            'platform' => $report->platform,
            'budget_requested' => (float) $report->budget_requested,
            'realization_amount' => (float) $report->realization_amount,
            'leads_count' => (int) $report->leads_count,
            'closing_count' => (int) $report->closing_count,
            'cpl' => DashboardNumbers::divide((float) $report->realization_amount, (float) $report->leads_count),
            'status' => $report->status,
            'has_attachment' => filled($report->attachment_path),
            'can_review' => $canReview,
            'can_complete' => $canComplete,
            'can_report_realization' => $canReportRealization,
        ];
    }

    /** @param Collection<int, RsmReport> $rows */
    private static function subtotal(Collection $rows): array
    {
        $requested = (float) $rows->sum('budget_requested');
        $realization = (float) $rows->sum('realization_amount');
        $leads = (float) $rows->sum('leads_count');
        $closing = (float) $rows->sum('closing_count');

        return [
            'count' => $rows->count(),
            'requested' => $requested,
            'realization' => $realization,
            'leads' => $leads,
            'closing' => $closing,
            'cpl' => DashboardNumbers::divide($realization, $leads),
        ];
    }
}
