<?php

namespace App\Http\Controllers;

use App\Exports\AdsRekapExport;
use App\Exports\RekapExport;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Dashboard\AchievementWhatsappService;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Services\Reports\ReportRecapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportRecapController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $area = $user->area ?: 'Regional B';
        $type = (string) $request->query('rekap_type', 'all');
        if (! in_array($type, ['all', RsmReport::TYPE_MARKETING, RsmReport::TYPE_ADS, RsmReport::TYPE_OTHER], true)) {
            $type = 'all';
        }
        $filters = DashboardFilters::fromRequest($request, 'rekap');
        $recap = ReportRecapService::build($area, $filters, $type, $user);

        return view('rekap.index', [
            'active' => 'rekap',
            'filters' => $filters,
            'type' => $type,
            'recap' => $recap,
            'references' => ReferenceOptionsService::build($area, $user),
            'whatsappArtifact' => AchievementWhatsappService::latest(),
        ]);
    }

    public function generateWhatsapp(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->role === 'super_user', 403);

        AchievementWhatsappService::generate($user->area ?: 'Regional B', $user);

        return back()->with('status', 'Bahan WhatsApp pencapaian terbaru berhasil dibuat.');
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $area = $user->area ?: 'Regional B';
        $type = (string) $request->query('rekap_type', 'all');
        if (! in_array($type, ['all', RsmReport::TYPE_MARKETING, RsmReport::TYPE_ADS, RsmReport::TYPE_OTHER], true)) {
            $type = 'all';
        }
        $filters = DashboardFilters::fromRequest($request, 'rekap');
        $recap = ReportRecapService::build($area, $filters, $type, $user);

        $format = (string) $request->query('format', $type === RsmReport::TYPE_ADS ? 'excel' : 'csv');
        if (! in_array($format, ['csv', 'excel', 'pdf'], true)) {
            $format = 'csv';
        }

        if ($format === 'pdf') {
            $filename = 'rekap-'.Str::slug($area).'-'.now()->format('Ymd-His').'.pdf';

            return Pdf::loadView('exports.rekap-pdf', [
                'area' => $area,
                'dateFrom' => $filters['date_from'],
                'dateTo' => $filters['date_to'],
                'type' => $type,
                'rows' => $recap['rows'],
            ])->download($filename);
        }

        if ($format === 'excel') {
            if ($type === RsmReport::TYPE_ADS) {
                $filename = 'rekap-laporan-iklan-'.Str::slug($area).'-'.now()->format('Ymd-His').'.xlsx';

                return Excel::download(new AdsRekapExport($area, $filters['date_from'], $filters['date_to'], $recap['rows']), $filename);
            }

            $filename = 'rekap-'.Str::slug($area).'-'.now()->format('Ymd-His').'.xlsx';

            return Excel::download(new RekapExport($area, $filters['date_from'], $filters['date_to'], $type, $recap['rows']), $filename);
        }

        $filename = 'rekap-'.Str::slug($area).'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($recap, $area, $filters, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Area', $area]);
            fputcsv($out, ['Periode', $filters['date_from'], $filters['date_to']]);
            fputcsv($out, ['Jenis Rekap', $type]);
            fputcsv($out, []);
            fputcsv($out, ['Tanggal', 'Jenis', 'Wilayah', 'Unit/Kampus', 'Staff', 'Judul/Campaign', 'Leads', 'Closing', 'Anggaran', 'Realisasi', 'Status']);
            foreach ($recap['rows'] as $row) {
                fputcsv($out, [$row['report_date'], $row['report_type'], $row['wilayah'], $row['unit_name'], $row['staff_name'], $row['title'], $row['leads_count'], $row['closing_count'], $row['budget_requested'], $row['realization_amount'], $row['status']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
