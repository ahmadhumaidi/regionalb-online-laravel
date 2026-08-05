<?php

namespace App\Http\Controllers;

use App\Models\RsmActivityLog;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Support\RsmRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ports the non-ads branch of rsm_update_status() (rsm_db.php:2491-2542),
 * shared by every plain report type (marketing/other — "Kegiatan
 * Marketing"/"Aktivitas Lain"). Ads reports use their own status machine
 * (budget_approved, plafon, Selesai) — see AdBudgetActionController.
 */
class ReportStatusController extends Controller
{
    private const KOORDINATOR_ACTIONABLE = ['Dikirim', 'Revisi'];

    private const SENIOR_ACTIONABLE = ['Diverifikasi', 'Revisi'];

    public function verify(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorizeKoordinator($report);
        $this->transition($request, $report, 'Diverifikasi', 'verifikasi');

        return back()->with('notice', 'Laporan diverifikasi.');
    }

    public function approve(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorizeSenior($report);
        $this->transition($request, $report, 'Disetujui', 'setujui');

        return back()->with('notice', 'Laporan disetujui.');
    }

    public function reject(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorizeSenior($report);
        $this->transition($request, $report, 'Ditolak', 'tolak');

        return back()->with('notice', 'Laporan ditolak.');
    }

    public function revise(Request $request, RsmReport $report): RedirectResponse
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        if ($user->role === 'koordinator') {
            $this->authorizeKoordinator($report);
        } else {
            $this->authorizeSenior($report);
        }

        $data = $request->validate(['note' => ['required', 'string']]);
        $this->transition($request, $report, 'Revisi', 'revisi', $data['note'], function () use ($report, $data) {
            $report->revision_note = $data['note'];
        });

        return back()->with('notice', 'Laporan dikembalikan untuk revisi.');
    }

    private function authorizeKoordinator(RsmReport $report): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_if($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless($user->role === 'koordinator' && $report->wilayah === $user->regional, 403);
        abort_unless(in_array($report->status, self::KOORDINATOR_ACTIONABLE, true), 422, 'Status laporan sudah berubah, muat ulang halaman.');
    }

    private function authorizeSenior(RsmReport $report): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_if($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless(RsmRole::canAction($user->role, 'setujui'), 403);
        abort_unless(in_array($report->status, self::SENIOR_ACTIONABLE, true), 422, 'Status laporan sudah berubah, muat ulang halaman.');
    }

    private function transition(Request $request, RsmReport $report, string $newStatus, string $action, ?string $note = null, ?callable $mutate = null): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $oldStatus = $report->status;

        $report->status = $newStatus;
        if ($mutate) {
            $mutate();
        }
        $report->save();

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
