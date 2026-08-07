<?php

namespace App\Http\Controllers;

use App\Services\CollabSourceService;
use App\Support\CollabTableRenderer;
use App\Support\RsmRole;
use App\Support\SyncHealth;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ports the "Sumber Data Collab" raw viewer (dashboard.php:987-1093): report
 * tabs, live vs archived-month period picker, and rowspan/colspan header
 * rendering via CollabTableRenderer.
 */
class CollabSourceController extends Controller
{
    private const MODE_LABELS = [
        'cache_auto' => 'Otomatis (cron)',
        'archive' => 'Arsip bulanan',
        'live_url' => 'Live',
        'backfill_range' => 'Backfill',
        'history_snapshot' => 'Snapshot histori',
        'live_url_uncached' => 'Live (tanpa cache)',
    ];

    private const MONTH_NAMES = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    public function index(Request $request): View
    {
        abort_unless(RsmRole::canSyncCollab($request->user()), 403);

        $snapshot = CollabSourceService::snapshot();
        $reportNames = array_keys($snapshot['reports']);

        $activeReport = (string) $request->query('report', '');
        if (! in_array($activeReport, $reportNames, true)) {
            $activeReport = $reportNames[0] ?? '';
        }

        $availableMonths = $activeReport !== '' ? CollabSourceService::availableMonths($activeReport) : [];
        $activeMonth = (string) $request->query('month', '');
        if ($activeMonth !== '' && ! in_array($activeMonth, $availableMonths, true)) {
            $activeMonth = '';
        }

        $activeReportData = $activeMonth !== ''
            ? CollabSourceService::archivedSnapshotEntry($activeReport, $activeMonth)
            : ($snapshot['reports'][$activeReport] ?? [
                'rows' => [], 'row_count' => 0, 'column_count' => 0,
                'source_url' => '', 'source_mode' => '', 'cached_at' => '', 'created_at' => '', 'error' => '',
            ]);

        $rows = (array) ($activeReportData['rows'] ?? []);
        $headerRowCount = 2;
        if ($rows !== []) {
            $layout = CollabSourceService::layout($rows);
            $headerRowCount = max(1, min((int) ($layout['data_start_index'] ?? 2), count($rows)));
        }

        $dataWidth = (int) ($activeReportData['column_count'] ?? 0);
        $headerRows = array_map(
            static fn (mixed $row): array => array_slice((array) $row, 0, $dataWidth > 0 ? $dataWidth : null),
            array_slice($rows, 0, $headerRowCount)
        );
        $dataRows = array_slice($rows, $headerRowCount, null, true);

        $modeRaw = (string) ($activeReportData['source_mode'] ?? '');

        return view('collab-source.index', [
            'active' => 'sumber-collab',
            'syncedAt' => $snapshot['synced_at'],
            'reportNames' => $reportNames,
            'activeReport' => $activeReport,
            'monthOptions' => array_map(fn (string $month) => ['value' => $month, 'label' => $this->monthLabel($month)], $availableMonths),
            'activeMonth' => $activeMonth,
            'reportData' => $activeReportData,
            'modeLabel' => self::MODE_LABELS[$modeRaw] ?? ($modeRaw !== '' ? $modeRaw : '-'),
            'headerRowsHtml' => CollabTableRenderer::headerRowsHtml($headerRows),
            'dataRowsHtml' => CollabTableRenderer::dataRowsHtml($dataRows),
            'syncHealth' => SyncHealth::status(),
        ]);
    }

    public function sync(Request $request)
    {
        abort_unless(RsmRole::canSyncCollab($request->user()), 403);
        CollabSourceService::sync();

        return redirect()->route('sumber-collab')->with('status', 'Snapshot Collab berhasil disegarkan.');
    }

    private function monthLabel(string $month): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            return $month;
        }

        return (self::MONTH_NAMES[$m[2]] ?? $m[2]).' '.$m[1];
    }
}
