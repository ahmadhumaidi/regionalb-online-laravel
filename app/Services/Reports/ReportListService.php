<?php

namespace App\Services\Reports;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\ReportScope;
use App\Support\RsmRole;

/**
 * Generic read-only list for the non-ads report types (marketing/other —
 * "Kegiatan Marketing" and "Aktivitas Lain"), which share one plain status
 * machine (Draft → Dikirim → Diverifikasi → Disetujui/Ditolak, Revisi as a
 * detour) instead of the ads-specific plafon/budget one. Ports the
 * `rsm_reports($area, $type, 50, $user)` call already used, unfiltered,
 * for both of these pages (dashboard.php:297/316).
 *
 * Current-status gates on the action flags below (Dikirim/Revisi for
 * koordinator, Diverifikasi/Revisi for senior-tier) aren't spelled out
 * verbatim in the legacy status-transition function — they're the
 * implied workflow order (koordinator verifies what's submitted, senior
 * approves what's verified) applied defensively, same reasoning already
 * used for the ads REVIEWABLE_STATUSES gate.
 */
class ReportListService
{
    private const KOORDINATOR_ACTIONABLE = ['Dikirim', 'Revisi'];

    private const SENIOR_ACTIONABLE = ['Diverifikasi', 'Revisi'];

    /** @return list<array> */
    public static function build(string $reportType, string $area, RsmUser $user): array
    {
        $reports = ReportScope::apply(
            RsmReport::query()->where('area', $area)->where('report_type', $reportType),
            $user
        )->orderByDesc('report_date')->orderByDesc('id')->limit(50)->get();

        return $reports->map(fn (RsmReport $report) => self::rowShape($report, $user))->all();
    }

    private static function rowShape(RsmReport $report, RsmUser $user): array
    {
        $status = (string) $report->status;

        $canKoordinatorAct = $user->role === 'koordinator'
            && $report->wilayah === $user->regional
            && in_array($status, self::KOORDINATOR_ACTIONABLE, true);

        $canSeniorAct = RsmRole::canAction($user->role, 'setujui')
            && in_array($status, self::SENIOR_ACTIONABLE, true);

        return [
            'id' => $report->id,
            'report_date' => optional($report->report_date)->format('d M Y') ?? '-',
            'wilayah' => $report->wilayah,
            'unit_name' => $report->unit_name,
            'staff_name' => $report->staff_name,
            'category' => $report->category ?: $report->activity_kind,
            'title' => $report->title,
            'result_text' => $report->result_text,
            'obstacle_text' => $report->obstacle_text,
            'follow_up_text' => $report->follow_up_text,
            'status' => $status,
            'has_attachment' => filled($report->attachment_path),
            'can_koordinator_act' => $canKoordinatorAct,
            'can_senior_act' => $canSeniorAct,
        ];
    }
}
