<?php

namespace App\Http\Controllers;

use App\Models\RsmActivityLog;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\XpService;
use App\Services\NotificationService;
use App\Support\RsmRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Aktivitas Lain" reports with a Kendala filled in get their own follow-up
 * flow, separate from the generic Verifikasi/Setujui/Tolak machine in
 * ReportStatusController: koordinator wilayah (default) → optionally
 * eskalasi ke Senior Manager → optionally eskalasi lagi ke
 * Mentor/Executive Director/Director. Whoever is currently responsible
 * (tracked via rsm_reports.escalated_to_role) can respond with a saran
 * tindak lanjut (→ Ditindak Lanjuti) or escalate further; koordinator or
 * the current handler can mark it Selesai. See NotificationService for the
 * notification side.
 */
class ObstacleFollowUpController extends Controller
{
    private const SENIOR_ROLES = ['super_user', 'executive_director', 'director', 'senior'];

    public function respond(Request $request, RsmReport $report): RedirectResponse
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $this->authorizeResponsible($report, $user);
        abort_unless($report->status === 'Dikirim', 422, 'Status laporan sudah berubah, muat ulang halaman.');

        $data = $request->validate([
            'saran_tindak_lanjut' => ['nullable', 'string'],
            'eskalasi_ke' => ['nullable', Rule::in(RsmRole::escalationTargetsFor($user->role))],
        ]);

        $saran = trim((string) ($data['saran_tindak_lanjut'] ?? ''));
        $eskalasiKe = $data['eskalasi_ke'] ?? null;

        if ($saran === '' && ! $eskalasiKe) {
            throw ValidationException::withMessages(['saran_tindak_lanjut' => 'Isi saran tindak lanjut, atau pilih eskalasi.']);
        }

        if ($eskalasiKe) {
            $this->transition($request, $report, $report->status, 'eskalasi', 'Eskalasi ke '.RsmRole::label($eskalasiKe), function () use ($report, $eskalasiKe) {
                $report->escalated_to_role = $eskalasiKe;
            });
            NotificationService::notifyEscalation($report, $eskalasiKe, $user);

            return back()->with('notice', 'Laporan dieskalasi ke '.RsmRole::label($eskalasiKe).'.');
        }

        $this->transition($request, $report, 'Ditindak Lanjuti', 'tindak_lanjut', $saran, function () use ($report, $saran) {
            $report->leader_follow_up_text = $saran;
            $report->escalated_to_role = null;
        });
        NotificationService::notifyStaffClosed($report, 'Ditindak Lanjuti');

        return back()->with('notice', 'Tindak lanjut tersimpan.');
    }

    public function complete(Request $request, RsmReport $report): RedirectResponse
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $this->authorizeResponsible($report, $user);
        abort_unless(in_array($report->status, ['Dikirim', 'Ditindak Lanjuti'], true), 422, 'Status laporan sudah berubah, muat ulang halaman.');

        $this->transition($request, $report, 'Selesai', 'selesai', null, function () use ($report) {
            $report->escalated_to_role = null;
        });
        NotificationService::notifyStaffClosed($report, 'Selesai');

        return back()->with('notice', 'Laporan ditandai selesai.');
    }

    private function authorizeResponsible(RsmReport $report, RsmUser $user): void
    {
        abort_if($report->report_type !== RsmReport::TYPE_OTHER, 404);
        abort_if(trim((string) $report->obstacle_text) === '', 404);
        abort_unless($report->area === ($user->area ?: 'Regional B'), 404);

        $isResponsible = in_array($user->role, self::SENIOR_ROLES, true)
            || ($report->escalated_to_role === null && $user->role === RsmUser::ROLE_KOORDINATOR && $report->wilayah === $user->regional)
            || ($report->escalated_to_role !== null && $user->role === $report->escalated_to_role);

        abort_unless($isResponsible, 403);
    }

    private function transition(Request $request, RsmReport $report, string $newStatus, string $action, ?string $note, ?callable $mutate = null): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $oldStatus = $report->status;

        $report->status = $newStatus;
        if ($mutate) {
            $mutate();
        }
        $report->save();
        XpService::syncReportEventXp($report);

        $originalId = (int) $request->session()->get('impersonation.original_id', 0);
        $impersonator = $originalId > 0 ? RsmUser::find($originalId) : null;

        RsmActivityLog::create([
            'report_id' => $report->id,
            'area' => $report->area,
            'actor_user_id' => $user->id,
            'actor_role' => $user->role,
            'actor_actual_role' => $user->role,
            'actor_view_role' => $user->role,
            'actor_name' => $user->name,
            'impersonator_user_id' => $impersonator?->id,
            'impersonator_name' => $impersonator?->name,
            'action_name' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'ip_address' => $request->ip(),
        ]);
    }
}
