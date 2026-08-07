<?php

namespace App\Http\Controllers;

use App\Exports\AdLeadTemplateExport;
use App\Models\RsmReport;
use App\Services\Reports\AdLeadImportService;
use App\Services\Reports\ReportFormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Data Hasil Iklan" (rsm_ad_leads) upload/bulk-update/template — ports the
 * detail-page panel at dashboard.php:1131-1148 (render_ad_leads_table()) and
 * the upload_ad_leads/update_ad_leads actions (rsm_db.php). Legacy scopes
 * upload_ad_leads only by area (no user scope) — we tighten that to the
 * normal report-visibility scope instead of reproducing the gap.
 */
class AdLeadController extends Controller
{
    public function upload(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorize($report);

        $request->validate([
            'ad_leads_file' => ['required', 'file', 'max:5120', 'mimes:xls,xlsx'],
        ]);

        $count = AdLeadImportService::import($report, $request->file('ad_leads_file'), replaceExisting: true);

        $notice = $count > 0
            ? "Data hasil iklan berhasil diupload ulang ({$count} baris)."
            : 'File berhasil diterima, tetapi tidak ada baris data yang terbaca.';

        return back()->with('notice', $notice);
    }

    public function update(Request $request, RsmReport $report): RedirectResponse
    {
        $this->authorize($report);

        $data = $request->validate([
            'lead_status' => ['array'],
            'lead_status.*' => ['nullable', 'string'],
            'lead_notes' => ['array'],
            'lead_notes.*' => ['nullable', 'string'],
        ]);

        AdLeadImportService::bulkUpdate($report, $data['lead_status'] ?? [], $data['lead_notes'] ?? []);

        return back()->with('notice', 'Data hasil iklan berhasil diperbarui.');
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new AdLeadTemplateExport, 'template-data-hasil-iklan.xlsx');
    }

    private function authorize(RsmReport $report): void
    {
        abort_unless($report->report_type === RsmReport::TYPE_ADS, 404);
        abort_unless(ReportFormService::visible($report, Auth::user()), 404);
    }
}
