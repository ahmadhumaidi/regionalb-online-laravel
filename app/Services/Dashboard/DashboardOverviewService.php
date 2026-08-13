<?php

namespace App\Services\Dashboard;

use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Support\Str;

/**
 * Ports rsm_dashboard_overview() (rsm_db.php:4491). Aggregation is done in
 * PHP over an eager-loaded, already-scoped/filtered report set rather than
 * as one large raw SQL join like the legacy version — this dashboard's
 * report volume is small enough (regional scale, per-month filtering) that
 * this stays simple and easy to verify, at the cost of doing the sums in
 * the app instead of the database.
 */
class DashboardOverviewService
{
    public static function build(string $area, array $filters, RsmUser $user): array
    {
        $reports = ScopedReports::query($area, $filters, $user)->with('adLeads')->get();

        $rawStatuses = $reports->flatMap(fn (RsmReport $report) => $report->adLeads->pluck('closing_status'))
            ->filter(fn ($value) => trim((string) $value) !== '');
        $buckets = ClosingStatusClassifier::buckets($rawStatuses);

        $rows = $reports->map(fn (RsmReport $report) => self::reportMetrics($report, $buckets['registrasi'], $buckets['herreg']));

        $leads = (float) $rows->sum('leads');
        $followUp = (float) $rows->sum('follow_up');
        $registrasi = (float) $rows->sum('registrasi');
        $herregistrasi = (float) $rows->sum('herregistrasi');

        $adsRows = $rows->filter(fn ($row) => $row['report_type'] === RsmReport::TYPE_ADS);
        $requested = (float) $adsRows->sum('budget_requested');
        $approved = (float) $adsRows->sum('budget_approved');
        $spend = (float) $adsRows->sum('realization_amount');
        $adsLeads = (float) $adsRows->sum('leads');
        $adsRegistrasi = (float) $adsRows->sum('registrasi');

        return [
            'kpi' => [
                'leads' => $leads,
                'follow_up' => $followUp,
                'registrasi' => $registrasi,
                'herregistrasi' => $herregistrasi,
                'conversion_rate' => DashboardNumbers::percent($registrasi, $leads),
            ],
            'funnel' => [
                ['label' => 'Leads', 'value' => $leads, 'rate' => 100.0],
                ['label' => 'Follow Up', 'value' => $followUp, 'rate' => DashboardNumbers::percent($followUp, $leads)],
                ['label' => 'Registrasi', 'value' => $registrasi, 'rate' => DashboardNumbers::percent($registrasi, $leads)],
                ['label' => 'Herregistrasi', 'value' => $herregistrasi, 'rate' => DashboardNumbers::percent($herregistrasi, $leads)],
            ],
            'budget' => [
                'requested' => $requested,
                'approved' => $approved,
                'spend' => $spend,
                'remaining' => $approved - $spend,
                'ads_leads' => $adsLeads,
                'ads_registrasi' => $adsRegistrasi,
                'cpl' => DashboardNumbers::divide($spend, $adsLeads),
                'cost_per_registrasi' => DashboardNumbers::divide($spend, $adsRegistrasi),
            ],
            'daily_reports' => self::dailyReports($area, $filters, $user),
        ];
    }

    /** @return array{leads: float, follow_up: float, registrasi: float, herregistrasi: float, report_type: string, budget_requested: float, budget_approved: float, realization_amount: float} */
    private static function reportMetrics(RsmReport $report, array $registrasiValues, array $herregValues): array
    {
        $leadRows = $report->adLeads;

        if ($leadRows->isNotEmpty()) {
            $leads = $leadRows->count();
            $followUp = $leadRows->filter(fn ($lead) => filled($lead->follow_up_result) || filled($lead->progress_status))->count();
            $registrasi = $leadRows->filter(fn ($lead) => in_array(mb_strtolower(trim((string) $lead->closing_status)), $registrasiValues, true))->count();
            $herreg = $leadRows->filter(fn ($lead) => in_array(mb_strtolower(trim((string) $lead->closing_status)), $herregValues, true))->count();
        } else {
            $leads = (int) $report->leads_count;
            $followUp = 0;
            $registrasi = (int) $report->closing_count;
            $herreg = 0;
        }

        return [
            'report_id' => $report->id,
            'leads' => $leads,
            'follow_up' => $followUp,
            'registrasi' => $registrasi,
            'herregistrasi' => $herreg,
            'report_type' => $report->report_type,
            'budget_requested' => (float) $report->budget_requested,
            'budget_approved' => (float) $report->budget_approved,
            'realization_amount' => (float) $report->realization_amount,
        ];
    }

    private static function dailyReports(string $area, array $filters, RsmUser $user): array
    {
        return ScopedReports::query($area, $filters, $user)
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get([
                'report_date', 'report_type', 'wilayah', 'unit_name', 'staff_name',
                'category', 'title', 'result_text', 'obstacle_text', 'follow_up_text', 'status',
            ])
            ->map(fn (RsmReport $report) => [
                'report_date' => optional($report->report_date)->format('d M Y') ?? '-',
                'report_type' => $report->report_type,
                'wilayah' => $report->wilayah,
                'unit_name' => $report->unit_name,
                'staff_name' => $report->staff_name,
                'category' => $report->category,
                'title' => $report->title ?: Str::limit((string) $report->result_text, 60),
                'result_text' => $report->result_text,
                'obstacle_text' => $report->obstacle_text,
                'follow_up_text' => $report->follow_up_text,
                'status' => $report->status,
            ])
            ->all();
    }
}
