<?php

namespace App\Services\Reports;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\ReportScope;
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
            'rows' => $reports->map(fn (RsmReport $report) => self::row($report))->all(),
            'summary' => self::summary($reports),
            'type' => $type,
        ];
    }

    private static function row(RsmReport $report): array
    {
        return [
            'id' => $report->id,
            'report_date' => optional($report->report_date)->format('d M Y') ?? '-',
            'report_type' => $report->report_type,
            'wilayah' => $report->wilayah,
            'unit_name' => $report->unit_name,
            'staff_name' => $report->staff_name,
            'title' => $report->campaign_name ?: $report->title,
            'leads_count' => (int) $report->leads_count,
            'closing_count' => (int) $report->closing_count,
            'budget_requested' => (float) $report->budget_requested,
            'realization_amount' => (float) $report->realization_amount,
            'status' => $report->status,
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
