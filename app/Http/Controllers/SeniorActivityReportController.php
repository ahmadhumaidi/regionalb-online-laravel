<?php

namespace App\Http\Controllers;

use App\Models\RsmSeniorActivityReport;
use App\Models\RsmUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SeniorActivityReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeRole($request->user());
        $type = in_array($request->query('type'), ['Kunjungan', 'Rapat'], true) ? $request->query('type') : '';

        $query = RsmSeniorActivityReport::query()
            ->where('area', $request->user()->area ?: 'Regional B')
            ->where('user_id', $request->user()->id);

        if ($type !== '') {
            $query->where('activity_type', $type);
        }
        if ($request->filled('month') && preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month'))) {
            $query->whereYear('activity_date', substr($request->query('month'), 0, 4))
                ->whereMonth('activity_date', substr($request->query('month'), 5, 2));
        }

        return view('senior-activity-reports.index', [
            'active' => 'laporan-senior',
            'reports' => $query->latest('activity_date')->latest('id')->paginate(20)->withQueryString(),
            'filters' => ['type' => $type, 'month' => (string) $request->query('month', '')],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeRole($request->user());

        return view('senior-activity-reports.form', [
            'active' => 'laporan-senior',
            'report' => new RsmSeniorActivityReport,
            'editing' => false,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeRole($request->user());
        $data = $this->validated($request);
        $data['area'] = $request->user()->area ?: 'Regional B';
        $data['user_id'] = $request->user()->id;
        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('senior-activity-reports', 'public');
        }
        RsmSeniorActivityReport::create($data);

        return redirect()->route('laporan-senior.index')->with('notice', 'Laporan kegiatan berhasil disimpan.');
    }

    public function edit(Request $request, RsmSeniorActivityReport $seniorReport)
    {
        $this->authorizeOwner($request->user(), $seniorReport);

        return view('senior-activity-reports.form', [
            'active' => 'laporan-senior', 'report' => $seniorReport, 'editing' => true,
        ]);
    }

    public function update(Request $request, RsmSeniorActivityReport $seniorReport)
    {
        $this->authorizeOwner($request->user(), $seniorReport);
        $data = $this->validated($request);
        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('senior-activity-reports', 'public');
        }
        $seniorReport->update($data);

        return redirect()->route('laporan-senior.index')->with('notice', 'Laporan kegiatan berhasil diperbarui.');
    }

    public function destroy(Request $request, RsmSeniorActivityReport $seniorReport)
    {
        $this->authorizeOwner($request->user(), $seniorReport);
        $seniorReport->delete();

        return redirect()->route('laporan-senior.index')->with('notice', 'Laporan kegiatan berhasil dihapus.');
    }

    public function attachment(Request $request, RsmSeniorActivityReport $seniorReport)
    {
        $this->authorizeOwner($request->user(), $seniorReport);
        abort_unless($seniorReport->attachment_path && Storage::disk('public')->exists($seniorReport->attachment_path), 404);

        return response()->file(Storage::disk('public')->path($seniorReport->attachment_path), ['Cache-Control' => 'private, max-age=300']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'activity_date' => ['required', 'date'],
            'activity_type' => ['required', Rule::in(['Kunjungan', 'Rapat'])],
            'title' => ['required', 'string', 'max:220'],
            'location' => ['nullable', 'string', 'max:220'],
            'participants' => ['nullable', 'string', 'max:2000'],
            'agenda' => ['required', 'string', 'max:5000'],
            'result_text' => ['required', 'string', 'max:5000'],
            'next_action' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);
    }

    private function authorizeRole(?RsmUser $user): void
    {
        abort_unless(in_array($user?->role, [RsmUser::ROLE_SENIOR, RsmUser::ROLE_SUPER_USER], true), 403);
    }

    private function authorizeOwner(?RsmUser $user, RsmSeniorActivityReport $report): void
    {
        $this->authorizeRole($user);
        abort_unless($report->user_id === $user->id && $report->area === ($user->area ?: 'Regional B'), 404);
    }
}
