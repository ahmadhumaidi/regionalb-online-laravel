<?php

namespace App\Services\Reports;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\DashboardNumbers;
use App\Services\Dashboard\ReportScope;
use App\Support\AreaRegionals;
use Illuminate\Support\Collection;

class ReportRecapService
{
    public static function build(string $area, array $filters, string $type, RsmUser $user): array
    {
        $query = RsmReport::query()->where('area', $area)
            ->whereBetween('report_date', [$filters['date_from'], $filters['date_to']]);

        if (in_array($type, [RsmReport::TYPE_MARKETING, RsmReport::TYPE_ADS, RsmReport::TYPE_OTHER], true)) {
            $query->where('report_type', $type);
        }
        foreach (['wilayah', 'unit_name', 'staff_name'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        $reports = ReportScope::apply($query, $user)->orderByDesc('report_date')->orderByDesc('id')->limit(300)->get();

        return [
            'rows' => $reports->map(fn (RsmReport $report) => self::row($report, $user))->all(),
            'summary' => self::summary($reports),
            'type' => $type,
        ];
    }

    private static function row(RsmReport $report, RsmUser $user): array
    {
        return [
            'id' => $report->id,
            'report_date' => optional($report->report_date)->format('d M Y') ?? '-',
            'report_type' => $report->report_type,
            'wilayah' => $report->wilayah,
            'unit_name' => $report->unit_name,
            'staff_name' => $report->staff_name,
            'title' => $report->campaign_name ?: $report->title,
            'ad_period' => $report->ad_period,
            'platform' => $report->platform,
            'campaign_name' => $report->campaign_name,
            'leads_count' => (int) $report->leads_count,
            'closing_count' => (int) $report->closing_count,
            'budget_requested' => (float) $report->budget_requested,
            'realization_amount' => (float) $report->realization_amount,
            'cpl' => (float) $report->cpl,
            'status' => $report->status,
            'can_edit' => ReportFormService::canEdit($report, $user),
            'can_delete' => ReportFormService::canDelete($report, $user),
        ];
    }

    /**
     * Groups already-fetched ads rows (from build()'s 'rows') by Regional →
     * Kampus with subtotals, for the "laporan iklan" Excel export — port of
     * ads_grouped_reports()/rekap_export_rows()'s ads branch
     * (dashboard.php:2539-2586), matching how the on-screen /anggaran table
     * is already grouped (AdBudgetReportsService::build()).
     */
    public static function groupAdsRows(array $rows, string $area): array
    {
        $order = AreaRegionals::forArea($area);

        $regionalGroups = collect($rows)
            ->groupBy(fn (array $row) => $row['wilayah'] ?: '-')
            ->map(fn (Collection $regionalRows, string $wilayah) => [
                'wilayah' => $wilayah,
                'campuses' => $regionalRows->groupBy(fn (array $row) => $row['unit_name'] ?: '-')
                    ->map(fn (Collection $campusRows, string $unit) => [
                        'label' => $unit,
                        'rows' => $campusRows->values()->all(),
                        'totals' => self::adsSubtotal($campusRows),
                    ])
                    ->sortBy('label')
                    ->values()
                    ->all(),
                'totals' => self::adsSubtotal($regionalRows),
            ])
            ->sortBy(function (array $group) use ($order) {
                $index = array_search($group['wilayah'], $order, true);

                return $index === false ? [1, $group['wilayah']] : [0, $index];
            })
            ->values();

        return $regionalGroups->all();
    }

    /** Grand total across all ads rows (any regional/kampus), for the export's bottom summary row. */
    public static function adsGrandTotal(array $rows): array
    {
        return self::adsSubtotal(collect($rows));
    }

    /** @param Collection<int, array> $rows */
    private static function adsSubtotal(Collection $rows): array
    {
        $requested = (float) $rows->sum('budget_requested');
        $realization = (float) $rows->sum('realization_amount');
        $leads = (float) $rows->sum('leads_count');
        $closing = (float) $rows->sum('closing_count');

        return [
            'count' => $rows->count(),
            'budget_requested' => $requested,
            'realization_amount' => $realization,
            'leads_count' => $leads,
            'closing_count' => $closing,
            'cpl' => DashboardNumbers::divide($realization, $leads),
        ];
    }

    private static function summary(Collection $reports): array
    {
        return [
            'count' => $reports->count(),
            'leads' => (int) $reports->sum('leads_count'),
            'closing' => (int) $reports->sum('closing_count'),
            'requested' => (float) $reports->sum('budget_requested'),
            'realization' => (float) $reports->sum('realization_amount'),
            'by_status' => $reports->countBy('status')->all(),
        ];
    }
}
