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
 * Ports the ads-specific branch of action_buttons()/rsm_update_status()
 * (dashboard.php:3730-3736, action_buttons()'s ads branch): Setujui/Tolak/
 * Revisi for super_user/executive_director/director/senior on a report
 * still in Pengajuan/Revisi. Realization reporting and the Disetujui→
 * Transfer/Invoice attachment trigger go through the generic
 * ReportFormController/ReportFormService edit flow instead — see
 * report_fields_for_type()/rsm_update_report() in the legacy source.
 */
class AdBudgetActionController extends Controller
{
    private const REVIEWABLE_STATUSES = ['Pengajuan', 'Revisi'];

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

    private function authorizeReview(RsmReport $report): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_unless($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless(RsmRole::canReviewAdBudgetRequest($user), 403);
        abort_unless(in_array($report->status, self::REVIEWABLE_STATUSES, true), 422, 'Status laporan sudah berubah, muat ulang halaman.');
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
