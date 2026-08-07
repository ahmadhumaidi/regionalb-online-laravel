<?php

namespace App\Http\Controllers;

use App\Models\RsmActivityLog;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\DashboardNumbers;
use App\Services\Reports\ReportFormService;
use App\Support\RsmRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Ports the ads-specific branch of action_buttons()/rsm_update_status()
 * (dashboard.php:4186-4227): Setujui/Tolak/Revisi for super_user/
 * executive_director/director/senior on a report still in Pengajuan/Revisi,
 * staff/koordinator reporting realization once budget is Disetujui, and
 * koordinator's single Selesai button once realization has been reported
 * (status Dilaporkan Unit).
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

    public function realisasiForm(RsmReport $report): View
    {
        $this->authorizeRealization($report);

        return view('anggaran.realisasi', ['report' => $report]);
    }

    public function realisasi(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorizeRealization($report);

        $data = $request->validate([
            'realization_amount' => ['required', 'numeric', 'min:0.01'],
            'leads_count' => ['required', 'integer', 'min:0'],
            'closing_count' => ['required', 'integer', 'min:0', 'lte:leads_count'],
            'attachment_path' => [$report->attachment_path ? 'nullable' : 'required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $this->transition($request, $report, 'Dilaporkan Unit', 'lapor_realisasi', null, function () use ($report, $data) {
            $report->realization_amount = $data['realization_amount'];
            $report->leads_count = $data['leads_count'];
            $report->closing_count = $data['closing_count'];
            $report->cpl = DashboardNumbers::divide((float) $data['realization_amount'], (float) $data['leads_count']);
        });

        ReportFormService::storeAttachment($report, $request->file('attachment_path'));

        return back()->with('notice', 'Realisasi iklan berhasil dilaporkan.');
    }

    public function complete(Request $request, RsmReport $report): RedirectResponse
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_unless($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless($user->role === 'koordinator' && $report->wilayah === $user->regional, 403);
        abort_unless(mb_strtolower(trim((string) $report->status)) === 'dilaporkan unit', 422, 'Laporan belum siap ditutup.');

        $this->transition($request, $report, 'Selesai', 'selesai');

        return back()->with('notice', 'Laporan realisasi ditandai selesai.');
    }

    private function authorizeRealization(RsmReport $report): void
    {
        /** @var RsmUser $user */
        $user = Auth::user();

        abort_unless($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless(in_array($user->role, ['staff', 'koordinator'], true) && $report->wilayah === $user->regional, 403);
        abort_unless(in_array(mb_strtolower(trim((string) $report->status)), ['disetujui', 'dilaporkan unit'], true), 422, 'Laporan belum disetujui atau sudah selesai.');
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
