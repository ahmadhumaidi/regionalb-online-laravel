<?php

namespace App\Http\Controllers;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Services\Dashboard\ReportScope;
use App\Services\Reports\AdLeadImportService;
use App\Services\Reports\ReportFormService;
use App\Services\Reports\ReportListService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportFormController extends Controller
{
    public function create(string $type): View
    {
        $user = Auth::user();
        $this->assertType($type);
        abort_unless(ReportFormService::canCreate($type, $user), 403);

        return $this->formView($type, new RsmReport, false, $user);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $user = Auth::user();
        $this->assertType($type);
        abort_unless(ReportFormService::canCreate($type, $user), 403);
        $data = $this->validated($request, $type, false, $user);
        ReportFormService::create($type, $data, $request->file('attachment_path'), $user);

        return redirect()->route($this->pageRoute($type))->with('notice', 'Laporan disimpan.');
    }

    public function show(RsmReport $report): View
    {
        $user = Auth::user();
        abort_unless(ReportFormService::visible($report, $user), 404);

        return view('reports.show', [
            'active' => ReportFormService::config($report->report_type)['label'],
            'report' => $report,
            'canEdit' => ReportFormService::canEdit($report, $user),
            'canDelete' => ReportFormService::canDelete($report, $user),
            'logs' => $report->logs()->latest('id')->get(),
            'adLeads' => $report->report_type === RsmReport::TYPE_ADS ? $report->adLeads()->orderBy('id')->get() : collect(),
        ]);
    }

    public function edit(RsmReport $report): View
    {
        $user = Auth::user();
        abort_unless(ReportFormService::canEdit($report, $user), 403);
        abort_unless(ReportFormService::visible($report, $user), 404);

        return $this->formView($report->report_type, $report, true, $user);
    }

    public function update(Request $request, RsmReport $report): RedirectResponse
    {
        $user = Auth::user();
        abort_unless(ReportFormService::canEdit($report, $user), 403);
        abort_unless(ReportFormService::visible($report, $user), 404);
        $data = $this->validated($request, $report->report_type, true, $user);
        ReportFormService::update($report, $data, $request->file('attachment_path'), $user, $request->file('insight_attachment_path'));

        if ($report->report_type === RsmReport::TYPE_ADS && $request->hasFile('ad_leads_file')) {
            AdLeadImportService::import($report->fresh(), $request->file('ad_leads_file'), replaceExisting: false);
        }

        if (
            $report->report_type === RsmReport::TYPE_ADS
            && $request->boolean('mark_selesai')
            && \App\Support\RsmRole::canManageAdBudget($user)
            && ! in_array($report->fresh()->status, ['Selesai', 'Ditolak'], true)
        ) {
            app(AdBudgetActionController::class)->markSelesai($request, $report->fresh());
        }

        return redirect()->route('reports.show', $report)->with('notice', 'Laporan diperbarui.');
    }

    public function destroy(RsmReport $report): RedirectResponse
    {
        $user = Auth::user();
        $type = $report->report_type;
        ReportFormService::delete($report, $user);

        return redirect()->route($this->pageRoute($type))->with('notice', 'Laporan dihapus.');
    }

    public function attachment(RsmReport $report)
    {
        abort_unless(ReportFormService::visible($report, Auth::user()), 404);
        abort_unless(filled($report->attachment_path), 404);

        if (Storage::disk('public')->exists($report->attachment_path)) {
            return Storage::disk('public')->response($report->attachment_path);
        }

        $legacyRoot = config('filesystems.legacy_public_root');
        $legacyPath = $legacyRoot ? realpath($legacyRoot.'/'.$report->attachment_path) : false;
        abort_unless($legacyPath && str_starts_with($legacyPath, realpath($legacyRoot)), 404);

        return response()->file($legacyPath);
    }

    /** "Bukti insight/pengeluaran iklan" — separate from attachment() (bukti transfer/invoice). */
    public function insightAttachment(RsmReport $report)
    {
        abort_unless(ReportFormService::visible($report, Auth::user()), 404);
        abort_unless(filled($report->insight_attachment_path), 404);
        abort_unless(Storage::disk('public')->exists($report->insight_attachment_path), 404);

        return Storage::disk('public')->response($report->insight_attachment_path);
    }

    private function formView(string $type, RsmReport $report, bool $editing, RsmUser $user): View
    {
        $config = ReportFormService::config($type);

        return view('reports.form', [
            'active' => $config['label'],
            'config' => $config,
            'report' => $report,
            'editing' => $editing,
            'user' => $user,
            'storeRoute' => $this->pageRoute($type).'.store',
            'references' => ReferenceOptionsService::build($user->area ?: 'Regional B', $user),
        ]);
    }

    private function validated(Request $request, string $type, bool $editing, RsmUser $user): array
    {
        if ($type === RsmReport::TYPE_ADS && $editing) {
            return $request->validate($this->adsEditRules($user));
        }

        $common = [
            'report_date' => ['required', 'date'],
            'wilayah' => ['nullable', 'string', 'max:120'],
            'unit_name' => ['nullable', 'string', 'max:180'],
            'staff_name' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', Rule::in($editing ? ['Draft', 'Dikirim', 'Revisi'] : ['Draft', 'Dikirim'])],
            'attachment_path' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
        $rules = match ($type) {
            RsmReport::TYPE_MARKETING => $common + [
                'activity_kind' => ['nullable', 'string', 'max:120'], 'title' => ['required', 'string', 'max:220'],
                'location_name' => ['nullable', 'string', 'max:220'], 'target_text' => ['nullable', 'string'], 'result_text' => ['nullable', 'string'],
                'leads_count' => ['nullable', 'integer', 'min:0'], 'notes' => ['nullable', 'string'],
            ],
            RsmReport::TYPE_OTHER => $common + [
                'category' => ['nullable', 'string', 'max:120'], 'title' => ['required', 'string', 'max:220'],
                'result_text' => ['nullable', 'string'], 'obstacle_text' => ['nullable', 'string'], 'follow_up_text' => ['nullable', 'string'],
            ],
            RsmReport::TYPE_ADS => $common + [
                'ad_period' => ['required', 'string', 'max:40'], 'platform' => ['required', 'string', 'max:120'],
                'campaign_name' => ['required', 'string', 'max:220'], 'ad_goal' => ['nullable', 'string', 'max:80'],
                'budget_requested' => ['required', 'numeric', 'min:0.01'],
            ],
            default => abort(404),
        };

        return $request->validate($rules);
    }

    /** Role-specific rules for editing an ads report, matching report_fields_for_type('ads', $role). */
    private function adsEditRules(RsmUser $user): array
    {
        if ($user->role === RsmUser::ROLE_STAFF) {
            return [
                'campaign_name' => ['required', 'string', 'max:220'],
                'ad_goal' => ['nullable', 'string', 'max:80'],
                'realization_amount' => ['nullable', 'numeric', 'min:0'],
                'campaign_link' => ['nullable', 'string', 'max:255'],
                'attachment_path' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
                'ad_leads_file' => ['nullable', 'file', 'max:5120', 'mimes:xls,xlsx'],
                'insight_attachment_path' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
                'notes' => ['nullable', 'string'],
            ];
        }

        $rules = [
            'report_date' => ['required', 'date'],
            'ad_period' => ['required', 'string', 'max:40'],
            'wilayah' => ['nullable', 'string', 'max:120'],
            'unit_name' => ['nullable', 'string', 'max:180'],
            'platform' => ['required', 'string', 'max:120'],
            'budget_requested' => ['required', 'numeric', 'min:0.01'],
            'attachment_path' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            'ad_leads_file' => ['nullable', 'file', 'max:5120', 'mimes:xls,xlsx'],
            'insight_attachment_path' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            'notes' => ['nullable', 'string'],
        ];

        if (in_array($user->role, ['super_user', 'executive_director', 'director', 'senior'], true)) {
            $rules['budget_approved'] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, [RsmReport::TYPE_MARKETING, RsmReport::TYPE_OTHER, RsmReport::TYPE_ADS], true), 404);
    }

    private function pageRoute(string $type): string
    {
        return ReportFormService::config($type)['label'];
    }
}
