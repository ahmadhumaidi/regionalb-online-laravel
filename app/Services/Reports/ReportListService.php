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
 *
 * "Aktivitas Lain" rows with a Kendala filled in skip this generic
 * verify/approve machine entirely and use the dedicated
 * tindak-lanjut/eskalasi flow instead (ObstacleFollowUpController) — see
 * the has_kendala-gated flags below.
 */
class ReportListService
{
    private const OBSTACLE_ACTIVE_STATUSES = ['Dikirim', 'Ditindak Lanjuti'];

    private const KOORDINATOR_ACTIONABLE = ['Dikirim', 'Revisi'];

    private const SENIOR_ACTIONABLE = ['Diverifikasi', 'Revisi'];

    private const SENIOR_ROLES = ['super_user', 'executive_director', 'director', 'senior'];

    /** @return list<array> */
    public static function build(string|array $reportType, string $area, RsmUser $user, array $filters = []): array
    {
        $reportTypes = (array) $reportType;
        $query = ReportScope::apply(
            RsmReport::query()->where('area', $area)->whereIn('report_type', $reportTypes),
            $user
        );

        $type = (string) ($filters['type'] ?? 'all');
        if ($type === 'marketing') {
            $query->where('report_type', RsmReport::TYPE_MARKETING);
        } elseif ($type === 'other') {
            $query->where('report_type', RsmReport::TYPE_OTHER)->where(fn ($q) => $q->whereNull('obstacle_text')->orWhere('obstacle_text', ''));
        } elseif ($type === 'kendala') {
            $query->where('report_type', RsmReport::TYPE_OTHER)->whereNotNull('obstacle_text')->where('obstacle_text', '<>', '');
        }
        $workflow = $type === 'kendala' ? (string) ($filters['workflow'] ?? '') : '';
        if ($workflow === 'active') {
            $query->whereIn('status', self::OBSTACLE_ACTIVE_STATUSES);
        } elseif ($workflow === 'korwil') {
            $query->whereNull('escalated_to_role')->whereIn('status', self::OBSTACLE_ACTIVE_STATUSES);
        } elseif ($workflow === 'senior') {
            $query->where('escalated_to_role', RsmUser::ROLE_SENIOR)->whereIn('status', self::OBSTACLE_ACTIVE_STATUSES);
        } elseif ($workflow === 'done') {
            $query->where('status', 'Selesai');
        }
        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate('report_date', '>=', $filters['date_from']);
        }
        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate('report_date', '<=', $filters['date_to']);
        }
        if (filled($filters['staff'] ?? null)) {
            $query->where('staff_name', 'like', '%'.trim((string) $filters['staff']).'%');
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        $reports = $query->orderByDesc('report_date')->orderByDesc('id')->limit(200)->get();

        $rows = $reports->map(fn (RsmReport $report) => self::shape($report, $user));
        if (($filters['action'] ?? '') === '1') {
            $rows = $rows->filter(fn (array $row) => $row['can_follow_up'] || $row['can_mark_selesai'] || $row['can_koordinator_act'] || $row['can_senior_act']);
        }

        return $rows->values()->all();
    }

    public static function shape(RsmReport $report, RsmUser $user): array
    {
        $status = (string) $report->status;
        $hasKendala = $report->report_type === RsmReport::TYPE_OTHER && trim((string) $report->obstacle_text) !== '';

        $canKoordinatorAct = ! $hasKendala
            && $user->role === 'koordinator'
            && $report->wilayah === $user->regional
            && in_array($status, self::KOORDINATOR_ACTIONABLE, true);

        $canSeniorAct = ! $hasKendala
            && RsmRole::canAction($user->role, 'setujui')
            && in_array($status, self::SENIOR_ACTIONABLE, true);

        $escalatedToRole = $hasKendala ? $report->escalated_to_role : null;
        $isOwningStaff = $user->role === RsmUser::ROLE_STAFF
            && $escalatedToRole === RsmUser::ROLE_STAFF
            && ((int) $report->user_id === (int) $user->id || $report->staff_name === $user->name);
        $isResponsible = $hasKendala && (
            $user->role === RsmUser::ROLE_SUPER_USER
            || $isOwningStaff
            || ($escalatedToRole === null && $user->role === 'koordinator' && $report->wilayah === $user->regional)
            || ($escalatedToRole !== null && $escalatedToRole !== RsmUser::ROLE_STAFF && $user->role === $escalatedToRole)
        );
        $canFollowUp = $isResponsible && $status === 'Dikirim';
        $canMarkSelesai = $isResponsible && in_array($status, ['Dikirim', 'Ditindak Lanjuti'], true);
        $needsAction = $canFollowUp || $canMarkSelesai || $canKoordinatorAct || $canSeniorAct;

        return [
            'id' => $report->id,
            'report_type' => $report->report_type,
            'report_type_label' => $report->report_type === RsmReport::TYPE_MARKETING ? 'Kegiatan Marketing' : 'Aktivitas Lain',
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
            'can_edit' => ReportFormService::canEdit($report, $user),
            'can_delete' => ReportFormService::canDelete($report, $user),
            'has_kendala' => $hasKendala,
            'escalated_to_role' => $escalatedToRole,
            'escalated_to_label' => $escalatedToRole ? RsmRole::label($escalatedToRole) : null,
            'can_follow_up' => $canFollowUp,
            'can_mark_selesai' => $canMarkSelesai,
            'needs_action' => $needsAction,
            'action_label' => $hasKendala ? 'Kendala perlu ditangani' : ($canKoordinatorAct ? 'Menunggu verifikasi' : ($canSeniorAct ? 'Menunggu persetujuan' : null)),
            'escalation_options' => $hasKendala ? RsmRole::escalationTargetsFor($user->role) : [],
        ];
    }
}
