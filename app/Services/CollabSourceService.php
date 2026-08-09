<?php

namespace App\Services;

use App\Models\RsmCollabReportArchive;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Ports rsm_collab_* from the legacy public_html/rsm_db.php so staging matches
 * production: authenticated fetch for the 4 gated cb.web.id reports, month
 * archiving into rsm_collab_report_archive, and daily metric ingestion into
 * rsm_collab_daily_metrics (the table CollabMetricsService reads for the main
 * Dashboard funnel/gamification).
 */
class CollabSourceService
{
    private const KNOWN_REPORTS = [
        'Closing Collab',
        'Herreg Collab',
        'Closing Kampus Regional',
        'Herreg Kampus Regional',
        'Closing Personal Per Regional',
        'Herreg Personal Per Regional',
        'Rekapitulasi PMB Periode Prioritas P2K',
    ];

    private const AUTHENTICATED_REPORTS = [
        'Closing Kampus Regional',
        'Herreg Kampus Regional',
        'Closing Personal Per Regional',
        'Herreg Personal Per Regional',
        'Rekapitulasi PMB Periode Prioritas P2K',
    ];

    private const DAILY_METRIC_REPORTS = [
        'Closing Collab',
        'Herreg Collab',
        'Closing Kampus Regional',
        'Herreg Kampus Regional',
        'Closing Personal Per Regional',
        'Herreg Personal Per Regional',
    ];

    private const SOURCE_URLS = [
        'Closing Collab' => 'https://cb.web.id/pencapaian_closing_collab_template.php',
        'Herreg Collab' => 'https://cb.web.id/pencapaian_herreg_collab_template.php',
        'Closing Kampus Regional' => 'https://cb.web.id/pencapaian_closing_perkampus_peregional.php',
        'Herreg Kampus Regional' => 'https://cb.web.id/pencapaian_closing_herreg_perkampus_peregional.php',
        'Closing Personal Per Regional' => 'https://cb.web.id/pencapaian_closing_personal_per_regional.php',
        'Herreg Personal Per Regional' => 'https://cb.web.id/pencapaian_closing_herreg_personal_per_regional.php',
        'Rekapitulasi PMB Periode Prioritas P2K' => 'https://cb.web.id/rekapitulasi_pencapaian_pmb_periode_prioritas.php?program=p2k',
    ];

    private const CACHE_KEY = 'collab_achievement.json';

    public static function knownReports(): array
    {
        return self::KNOWN_REPORTS;
    }

    public static function sourceUrl(string $reportName): string
    {
        return self::SOURCE_URLS[$reportName] ?? '';
    }

    public static function layout(array $rows): array
    {
        $monthRowIndex = null;
        $dateRowIndex = null;
        $month = '';
        foreach ($rows as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                $label = trim((string) $cell);
                if ($month === '' && preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec|januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\b\s+\d{4}/i', $label, $match)) {
                    $month = $match[0];
                    $monthRowIndex = (int) $rowIndex;
                }
            }
        }

        if ($monthRowIndex !== null) {
            $candidateRow = $rows[$monthRowIndex + 1] ?? null;
            if (is_array($candidateRow)) {
                [$candidateDayIndexes] = self::dayIndexes($candidateRow);
                if ($candidateDayIndexes !== []) {
                    $dateRowIndex = $monthRowIndex + 1;
                }
            }
        }
        if ($dateRowIndex === null) {
            foreach ($rows as $rowIndex => $row) {
                if (! is_array($row)) {
                    continue;
                }
                [$dayIndexesFound] = self::dayIndexes($row);
                if ($dayIndexesFound !== [] && count($dayIndexesFound) >= 3) {
                    $dateRowIndex = (int) $rowIndex;
                    break;
                }
            }
        }

        $headerRow = $dateRowIndex !== null ? ($rows[$dateRowIndex] ?? []) : [];
        [$dayIndexes, $totalIndex] = is_array($headerRow) ? self::dayIndexes($headerRow) : [[], null];
        if ($totalIndex === null && $dayIndexes !== []) {
            $totalIndex = max($dayIndexes) + 1;
        }

        $lowerHeader = array_map(static fn (mixed $value): string => strtolower(trim((string) $value)), is_array($headerRow) ? $headerRow : []);
        $findHeader = static function (array $needles) use ($lowerHeader): ?int {
            foreach ($lowerHeader as $index => $label) {
                foreach ($needles as $needle) {
                    if ($label === $needle || str_contains($label, $needle)) {
                        return (int) $index;
                    }
                }
            }

            return null;
        };

        return [
            'month_label' => $month,
            'month_row_index' => $monthRowIndex,
            'date_row_index' => $dateRowIndex,
            'data_start_index' => $dateRowIndex !== null ? $dateRowIndex + 1 : 2,
            'day_indexes' => $dayIndexes,
            'total_index' => $totalIndex,
            'regional_index' => $findHeader(['wilayah']) ?? 0,
            'staff_nik_index' => $findHeader(['nik staff']) ?? 3,
            'staff_name_index' => $findHeader(['nama staff']) ?? 4,
        ];
    }

    public static function reportMonth(array $rows): string
    {
        $layout = self::layout($rows);
        $label = trim((string) ($layout['month_label'] ?? ''));
        if ($label === '') {
            return '';
        }

        $timestamp = strtotime('1 '.$label);

        return $timestamp ? date('Y-m', $timestamp) : '';
    }

    public static function availableMonths(string $reportName): array
    {
        return RsmCollabReportArchive::where('report_name', $reportName)
            ->orderByDesc('report_month')
            ->pluck('report_month')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    public static function snapshot(): array
    {
        $cache = self::cacheRead();
        $reports = (array) ($cache['reports'] ?? []);
        $errors = (array) ($cache['errors'] ?? []);

        $snapshot = [];
        foreach (self::knownReports() as $reportName) {
            $report = (array) ($reports[$reportName] ?? []);
            $snapshot[$reportName] = self::buildSnapshotEntry($reportName, $report, (string) ($errors[$reportName] ?? ''));
        }

        return [
            'synced_at' => (string) ($cache['synced_at'] ?? ''),
            'reports' => $snapshot,
        ];
    }

    public static function archivedSnapshotEntry(string $reportName, string $month): array
    {
        $report = self::reportFromArchive($reportName, $month);
        if ($report === []) {
            return self::buildSnapshotEntry($reportName, [], 'Belum ada arsip untuk bulan ini.');
        }

        return self::buildSnapshotEntry($reportName, $report);
    }

    /**
     * @param  int|null  $windowDays  Batasi ingest rsm_collab_daily_metrics ke
     *      N hari terakhir (hari ini inklusif) — dipakai oleh cron 30-menitan
     *      supaya hari yang sudah lewat window tidak ditulis ulang terus.
     *      null = ingest penuh (full sync harian & sync manual dari UI).
     */
    public static function sync(?int $windowDays = null): array
    {
        $syncedAt = now()->format('Y-m-d H:i:s');
        $result = [
            'synced_at' => $syncedAt,
            'synced_at_unix' => now()->timestamp,
            'reports' => [],
            'errors' => [],
        ];

        foreach (self::knownReports() as $reportName) {
            $report = self::reportFromUrl($reportName);
            if ($report === []) {
                $result['errors'][$reportName] = 'Source tidak terbaca saat sinkronisasi.';

                continue;
            }
            $report['source_mode'] = 'cache_auto';
            $report['cached_at'] = $syncedAt;
            $result['reports'][$reportName] = $report;
            self::archiveReport($reportName, $report);
            if (in_array($reportName, self::DAILY_METRIC_REPORTS, true)) {
                self::ingestDailyMetrics($reportName, $report, $windowDays);
            }
        }

        $existing = self::cacheRead();
        if ($result['reports'] === []) {
            if ($existing !== []) {
                $existing['last_sync_error_at'] = $syncedAt;
                $existing['last_sync_errors'] = $result['errors'];
                self::cacheWrite($existing);
            }

            return $result;
        }

        foreach ((array) ($existing['reports'] ?? []) as $reportName => $report) {
            if (! isset($result['reports'][$reportName]) && is_array($report)) {
                $result['reports'][$reportName] = $report;
            }
        }
        self::cacheWrite($result);

        return $result;
    }

    private static function buildSnapshotEntry(string $reportName, array $report, string $error = ''): array
    {
        $rows = $report['tables'][0] ?? [];
        $rows = is_array($rows) ? $rows : [];

        $dataStart = 0;
        if ($rows !== []) {
            $layout = self::layout($rows);
            $dataStart = max(0, min((int) ($layout['data_start_index'] ?? 0), count($rows) - 1));
        }

        $columnCount = 0;
        foreach (array_slice($rows, $dataStart, null, true) as $row) {
            if (is_array($row)) {
                $columnCount = max($columnCount, count($row));
            }
        }
        if ($columnCount === 0) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $columnCount = max($columnCount, count($row));
                }
            }
        }

        return [
            'name' => $reportName,
            'source_url' => (string) ($report['source_url'] ?? self::sourceUrl($reportName)),
            'source_mode' => (string) ($report['source_mode'] ?? ''),
            'cached_at' => (string) ($report['cached_at'] ?? ''),
            'created_at' => (string) ($report['created_at'] ?? ''),
            'error' => $error,
            'rows' => $rows,
            'row_count' => count($rows),
            'column_count' => $columnCount,
        ];
    }

    private static function reportFromUrl(string $reportName): array
    {
        $url = self::sourceUrl($reportName);
        if ($url === '') {
            return [];
        }

        $isAuthenticated = in_array($reportName, self::AUTHENTICATED_REPORTS, true);
        $html = $isAuthenticated ? self::authenticatedHtml($url) : self::plainHtml($url);
        if (! $isAuthenticated && trim($html) === '') {
            usleep(500000);
            $html = self::plainHtml($url);
        }
        if (trim($html) === '') {
            return [];
        }

        $tables = self::parseHtmlTables($html);
        if ($tables === []) {
            return [];
        }

        return [
            'name' => $reportName,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'source_url' => $url,
            'source_mode' => 'live_url',
            'tables' => $tables,
        ];
    }

    /** Public so other Collab-sourced sync services (e.g. PersonnelScheduleService) can reuse it. */
    public static function credentials(): ?array
    {
        $username = trim((string) config('services.collab.username'));
        $password = trim((string) config('services.collab.password'));
        if ($username === '' || $password === '') {
            return null;
        }

        return ['username' => $username, 'password' => $password];
    }

    /** Public so other Collab-sourced sync services (e.g. PersonnelScheduleService) can reuse it. */
    public static function authenticatedHtml(string $url): string
    {
        $credentials = self::credentials();
        if ($credentials === null) {
            return '';
        }

        try {
            $jar = new CookieJar;
            $headers = ['Accept' => 'text/html,application/xhtml+xml', 'User-Agent' => 'RegionalB-Dashboard/1.0'];

            Http::withOptions(['cookies' => $jar])
                ->timeout(20)->connectTimeout(10)->withHeaders($headers)
                ->asForm()->post($url, [
                    'acc_username' => $credentials['username'],
                    'acc_password' => $credentials['password'],
                    'bsignin' => '',
                ]);

            $response = Http::withOptions(['cookies' => $jar])
                ->timeout(20)->connectTimeout(10)->withHeaders($headers)
                ->get($url);

            return $response->body();
        } catch (\Throwable) {
            return '';
        }
    }

    private static function plainHtml(string $url): string
    {
        try {
            return Http::timeout(20)->connectTimeout(10)
                ->withHeaders(['Accept' => 'text/html,application/xhtml+xml', 'User-Agent' => 'RegionalB-Dashboard/1.0'])
                ->get($url)->body();
        } catch (\Throwable) {
            return '';
        }
    }

    private static function dayIndexes(array $headerRow): array
    {
        $dayIndexes = [];
        $totalIndex = null;
        foreach ($headerRow as $index => $label) {
            $label = trim((string) $label);
            if (preg_match('/^\d{1,2}$/', $label)) {
                $dayIndexes[] = (int) $index;

                continue;
            }
            if (strtolower($label) === 'total') {
                $totalIndex = (int) $index;
            }
        }

        return [$dayIndexes, $totalIndex];
    }

    private static function parseHtmlTables(string $html): array
    {
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        $tables = [];
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $loaded = @$dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded) {
            foreach ($dom->getElementsByTagName('table') as $table) {
                $rows = [];
                $rowSpans = [];
                foreach ($table->getElementsByTagName('tr') as $tr) {
                    $tds = [];
                    foreach ($tr->childNodes as $node) {
                        if (in_array(strtolower((string) $node->nodeName), ['td', 'th'], true)) {
                            $tds[] = $node;
                        }
                    }

                    if (count($tds) === 1 && (int) $tds[0]->getAttribute('colspan') > 1) {
                        continue;
                    }

                    $cells = [];
                    $col = 0;
                    $tdIndex = 0;
                    while ($tdIndex < count($tds) || isset($rowSpans[$col])) {
                        if (isset($rowSpans[$col]) && $rowSpans[$col]['remaining'] > 0) {
                            $cells[$col] = $rowSpans[$col]['value'];
                            $rowSpans[$col]['remaining']--;
                            if ($rowSpans[$col]['remaining'] <= 0) {
                                unset($rowSpans[$col]);
                            }
                            $col++;

                            continue;
                        }
                        if ($tdIndex >= count($tds)) {
                            break;
                        }
                        $node = $tds[$tdIndex];
                        $tdIndex++;
                        $text = trim(preg_replace('/\s+/', ' ', (string) $node->textContent) ?? '');
                        $colspan = max(1, (int) $node->getAttribute('colspan') ?: 1);
                        $rowspan = max(1, (int) $node->getAttribute('rowspan') ?: 1);
                        for ($i = 0; $i < $colspan; $i++) {
                            $cells[$col] = $text;
                            if ($rowspan > 1) {
                                $rowSpans[$col] = ['value' => $text, 'remaining' => $rowspan - 1];
                            }
                            $col++;
                        }
                    }

                    ksort($cells);
                    $cells = array_values($cells);
                    if ($cells !== []) {
                        $rows[] = $cells;
                    }
                }
                if (count($rows) >= 3) {
                    $tables[] = $rows;
                }
            }
        }

        if ($tables === [] && preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $matches)) {
            $rows = [];
            foreach ($matches[1] as $rowHtml) {
                if (! preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $rowHtml, $cellMatches)) {
                    continue;
                }
                $cells = array_map(static function (string $cellHtml): string {
                    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cellHtml), ENT_QUOTES, 'UTF-8')) ?? '');
                }, $cellMatches[1]);
                if ($cells !== []) {
                    $rows[] = $cells;
                }
            }
            if (count($rows) >= 3) {
                $tables[] = $rows;
            }
        }

        if ($tables === []) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) ?: [])));
            $rows = [];
            foreach ($lines as $line) {
                if (! str_contains($line, '|')) {
                    continue;
                }
                $cells = array_map('trim', explode('|', $line));
                if (count($cells) >= 5) {
                    $rows[] = $cells;
                }
            }
            if (count($rows) >= 3) {
                $tables[] = $rows;
            }
        }

        return $tables;
    }

    private static function archiveReport(string $reportName, array $report): void
    {
        $rows = $report['tables'][0] ?? [];
        if (! is_array($rows) || count($rows) < 3) {
            return;
        }
        $month = self::reportMonth($rows);
        if ($month === '') {
            return;
        }
        $payload = json_encode($report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        RsmCollabReportArchive::updateOrCreate(
            ['report_name' => $reportName, 'report_month' => $month],
            ['payload' => $payload]
        );
    }

    private static function reportFromArchive(string $reportName, string $month): array
    {
        if ($month === '') {
            return [];
        }
        $row = RsmCollabReportArchive::where('report_name', $reportName)->where('report_month', $month)->first();
        if (! $row) {
            return [];
        }
        $decoded = json_decode((string) $row->payload, true);
        if (! is_array($decoded) || empty($decoded['tables'][0]) || ! is_array($decoded['tables'][0])) {
            return [];
        }
        $decoded['source_mode'] = 'archive';

        return $decoded;
    }

    private static function ingestDailyMetrics(string $reportName, array $report, ?int $windowDays = null): void
    {
        $rows = $report['tables'][0] ?? [];
        if (! is_array($rows) || count($rows) < 3) {
            return;
        }
        $reportMonth = self::reportMonth($rows);
        if ($reportMonth === '') {
            return;
        }

        $layout = self::layout($rows);
        $dateHeaderRow = $layout['date_row_index'] !== null ? ($rows[$layout['date_row_index']] ?? []) : [];
        if (! is_array($dateHeaderRow) || $dateHeaderRow === []) {
            return;
        }

        $isCampus = in_array($reportName, ['Closing Kampus Regional', 'Herreg Kampus Regional'], true);
        $isPersonalRegional = in_array($reportName, ['Closing Personal Per Regional', 'Herreg Personal Per Regional'], true);
        $valueBase = $isCampus ? 4 : 5;

        $dayValueIndexes = [];
        $offset = 0;
        foreach ($layout['day_indexes'] as $headerIndex) {
            $dayLabel = trim((string) ($dateHeaderRow[$headerIndex] ?? ''));
            if (! preg_match('/^\d{1,2}$/', $dayLabel)) {
                continue;
            }
            $dayValueIndexes[(int) $dayLabel] = $isPersonalRegional ? $headerIndex : $valueBase + $offset;
            $offset++;
        }
        if ($dayValueIndexes === []) {
            return;
        }

        $syncCutoff = $windowDays !== null
            ? now()->startOfDay()->subDays(max(0, $windowDays - 1))->format('Y-m-d')
            : null;

        $now = now();
        $records = [];
        $currentRegional = null;

        foreach ($rows as $index => $row) {
            if ($index < (int) ($layout['data_start_index'] ?? 0) || ! is_array($row)) {
                continue;
            }

            if ($isCampus) {
                if (count($row) < 5) {
                    continue;
                }
                $regionalLabel = trim((string) ($row[2] ?? ''));
                $campusName = trim((string) ($row[3] ?? ''));
                if ($campusName === '' || stripos($campusName, 'Total ') === 0 || ! preg_match('/Regional\s+([1-7])/i', $regionalLabel, $match)) {
                    continue;
                }
                $regional = 'Regional '.$match[1];
                $entityKey = mb_strtolower($regional.'|'.$campusName);
                $staffNik = null;
                $staffName = null;
            } elseif ($isPersonalRegional) {
                $label = trim((string) ($row[0] ?? ''));
                if ($label === '') {
                    continue;
                }
                if (preg_match('/\(Korwil Regional\s*([1-7])\)\s*$/i', $label, $korwilMatch)) {
                    $currentRegional = 'Regional '.$korwilMatch[1];
                }
                if (preg_match('/^(Sub\.?\s*Total|Total\s|Grand\.?\s*Total)/i', $label)) {
                    if (preg_match('/\(Korwil Regional\s*[1-7]?\)/i', $label)) {
                        $currentRegional = null;
                    }

                    continue;
                }
                if ($currentRegional === null) {
                    continue;
                }
                $clean = trim((string) preg_replace('/\s*\(Korwil Regional[^)]*\)\s*$/i', '', $label));
                $staffNik = null;
                $staffName = $clean;
                if (preg_match('/^([A-Za-z]{1,4}[.\d-]+)\s*-\s*(.+)$/', $clean, $nameMatch)) {
                    $staffNik = trim($nameMatch[1]);
                    $staffName = trim($nameMatch[2]);
                }
                if ($staffName === '') {
                    continue;
                }
                $regional = $currentRegional;
                $entityKey = self::usernameFromNikOrName($staffNik, $staffName);
                $campusName = null;
            } else {
                $regionalDigit = trim((string) ($row[(int) $layout['regional_index']] ?? ''));
                $staffNik = trim((string) ($row[(int) $layout['staff_nik_index']] ?? ''));
                $staffName = trim((string) ($row[(int) $layout['staff_name_index']] ?? ''));
                if (! preg_match('/^SG[.\d-]+$/i', $staffNik) && preg_match('/^SG[.\d-]+$/i', trim((string) ($row[(int) $layout['staff_nik_index'] + 1] ?? '')))) {
                    $staffNik = trim((string) ($row[(int) $layout['staff_nik_index'] + 1] ?? ''));
                    $staffName = trim((string) ($row[(int) $layout['staff_name_index'] + 1] ?? $staffName));
                }
                if ($staffName === '' || ! preg_match('/^[1-7]$/', $regionalDigit)) {
                    continue;
                }
                $regional = 'Regional '.$regionalDigit;
                $entityKey = self::usernameFromNikOrName($staffNik !== '' ? $staffNik : null, $staffName);
                $campusName = null;
            }

            foreach ($dayValueIndexes as $dayNumber => $valueIndex) {
                $metricDate = $reportMonth.'-'.str_pad((string) $dayNumber, 2, '0', STR_PAD_LEFT);
                if ($syncCutoff !== null && $metricDate < $syncCutoff) {
                    continue;
                }
                $value = self::numberValue($row[$valueIndex] ?? 0);
                $records[] = [
                    'report_name' => $reportName,
                    'metric_date' => $metricDate,
                    'entity_key' => $entityKey,
                    'staff_nik' => $staffNik,
                    'staff_name' => $staffName,
                    'regional' => $regional,
                    'campus_name' => $campusName,
                    'value' => $value,
                    'synced_at' => $now,
                ];
            }
        }

        if ($records === []) {
            return;
        }

        DB::transaction(function () use ($records) {
            foreach (array_chunk($records, 500) as $chunk) {
                DB::table('rsm_collab_daily_metrics')->upsert(
                    $chunk,
                    ['report_name', 'metric_date', 'entity_key'],
                    ['staff_nik', 'staff_name', 'regional', 'campus_name', 'value', 'synced_at']
                );
            }
        });
    }

    private static function usernameFromNikOrName(?string $nik, string $name): string
    {
        if ($nik !== null && trim($nik) !== '') {
            return str_replace('.', '', trim($nik));
        }
        $username = strtolower(trim($name));
        $username = preg_replace('/[^a-z0-9]+/i', '', $username) ?: $username;

        return $username !== '' ? $username : 'rsmuser';
    }

    /**
     * "Rekapitulasi PMB Periode Prioritas P2K" isn't ingested into
     * rsm_collab_daily_metrics (see DAILY_METRIC_REPORTS) — it's a raw
     * two-semester table (cols 2/3 = Daftar/Heregistrasi for the Ganjil
     * period, 8/9 for Genap) with "Total Regional N - <Korwil>" subtotal
     * rows. Used by the Dashboard's "Target PMB" card: sums Daftar and
     * Heregistrasi (both periods) across just the given regional numbers.
     *
     * @param  list<string>  $regionals  e.g. ['Regional 4', 'Regional 5', 'Regional 6', 'Regional 7']
     * @return array{daftar: float, herreg: float}
     */
    public static function pmbRegionalTotals(array $regionals): array
    {
        $rows = self::snapshot()['reports']['Rekapitulasi PMB Periode Prioritas P2K']['rows'] ?? [];
        $numbers = array_map(static fn (string $r): int => (int) preg_replace('/\D+/', '', $r), $regionals);

        $daftar = 0.0;
        $herreg = 0.0;
        foreach ($rows as $row) {
            $label = trim((string) ($row[0] ?? ''));
            if (! preg_match('/^Total Regional\s+(\d+)/i', $label, $match) || ! in_array((int) $match[1], $numbers, true)) {
                continue;
            }
            $daftar += self::numberValue($row[2] ?? 0) + self::numberValue($row[8] ?? 0);
            $herreg += self::numberValue($row[3] ?? 0) + self::numberValue($row[9] ?? 0);
        }

        return ['daftar' => $daftar, 'herreg' => $herreg];
    }

    private static function numberValue(mixed $value): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private static function cacheRead(): array
    {
        if (Storage::disk('local')->exists(self::CACHE_KEY)) {
            $decoded = json_decode((string) Storage::disk('local')->get(self::CACHE_KEY), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $legacy = base_path('../regionalb.online/public_html/runtime/cache/collab_achievement.json');
        if (is_file($legacy)) {
            $decoded = json_decode((string) file_get_contents($legacy), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private static function cacheWrite(array $payload): void
    {
        Storage::disk('local')->put(self::CACHE_KEY, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
