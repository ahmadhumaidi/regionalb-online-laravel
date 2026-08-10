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
 * Full ads status machine (per Ahmad Humaidi's workflow description):
 * Pengajuan --(Setujui/Tolak/Revisi, senior tier)--> Disetujui/Ditolak/Revisi
 * --(staff/korwil upload bukti)--> Dilaporkan Unit --(korwil/senior verifies
 * the *report*, not the budget request)--> Diverifikasi --(senior tier marks
 * complete)--> Selesai. Ports the ads-specific branch of action_buttons()/
 * rsm_update_status() (dashboard.php:3730-3736) for the Setujui/Tolak/Revisi
 * leg; realization reporting goes through ReportFormController/
 * ReportFormService's edit flow instead.
 */
class AdBudgetActionController extends Controller
{
    private const REVIEWABLE_STATUSES = ['Pengajuan', 'Revisi'];

    private const VERIFIABLE_STATUSES = ['Dilaporkan Unit'];

    public function verify(Request $request, RsmReport $report): RedirectResponse
    {
        $this->markVerified($request, $report);

        return back()->with('notice', 'Laporan diverifikasi.');
    }

    /** Shared by the dedicated "Verifikasi" action above and the ads edit form's optional verify checkbox (ReportFormController::update()). Confirms the staff/korwil-reported evidence, not the original budget request. */
    public function markVerified(Request $request, RsmReport $report): void
    {
        $this->authorizeVerify($report);

        $this->transition($request, $report, 'Diverifikasi', 'verifikasi');
    }

    public function approve(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorizeReview($report);

        $data = $request->validate([
            'budget_approved' => ['required', 'numeric', 'min:0'],
        ]);

        $this->transition($request, $report, 'Disetujui', 'setujui', null, function () use ($report, $data) {
            $report->budget_approved = $data['budget_approved'];
        });

        return back()->with('notice', 'Pengajuan anggaran disetujui.');
    }

    public function reject(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorizeReview($report);

        $this->transition($request, $report, 'Ditolak', 'tolak');

        return back()->with('notice', 'Pengajuan anggaran ditolak.');
    }

    public function revise(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorizeReview($report);

        $data = $request->validate([
            'note' => ['required', 'string'],
        ]);

        $this->transition($request, $report, 'Revisi', 'revisi', $data['note'], function () use ($report, $data) {
            $report->revision_note = $data['note'];
        });

        return back()->with('notice', 'Pengajuan dikembalikan untuk revisi.');
    }

    /** Marking an ads report "Selesai" is reserved for canManageAdBudget() (super_user/senior), not the wider canReviewAdBudgetRequest() tier — and only once korwil/senior has verified the reported evidence. */
    public function complete(Request $request, RsmReport $report): RedirectResponse
    {
        $this->markSelesai($request, $report);

        return back()->with('notice', 'Laporan iklan ditandai selesai.');
    }

    /** Shared by the dedicated "Tandai Selesai" action above and the ads edit form's optional mark_selesai checkbox (ReportFormController::update()). */
    public function markSelesai(Request $request, RsmReport $report): void
    {
        $this->authorizeComplete($report);

        $this->transition($request, $report, 'Selesai', 'selesai');
    }

    private function authorizeReview(RsmReport $report): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_unless($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless(RsmRole::canReviewAdBudgetRequest($user), 403);
        abort_unless(in_array($report->status, self::REVIEWABLE_STATUSES, true), 422, 'Status laporan sudah berubah, muat ulang halaman.');
    }

    private function authorizeVerify(RsmReport $report): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_unless($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless(RsmRole::canVerifyAdBudgetRequest($report, $user), 403);
        abort_unless(in_array($report->status, self::VERIFIABLE_STATUSES, true), 422, 'Status laporan sudah berubah, muat ulang halaman.');
    }

    private function authorizeComplete(RsmReport $report): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_unless($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless(RsmRole::canManageAdBudget($user), 403);
        abort_unless($report->status === 'Diverifikasi', 422, 'Laporan harus diverifikasi korwil terlebih dahulu.');
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
