<?php

namespace App\Services;

use App\Support\AreaRegionals;
use App\Support\CampusMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Ports rsm_bdc_report_users()/rsm_bdc_store_report_users()
 * (rsm_db.php:4310-4474): unauthenticated GET against api.p2k.co.id,
 * cached locally with a 15-minute TTL (matching legacy), and upserted into
 * rsm_bdc_report_user_snapshots — the table SyncHealth reads for the "BDC
 * Marketing" health card. Only Regional B wilayah rows are stored, matching
 * legacy (this app only ever served Regional B).
 */
class BdcReportUsersService
{
    private const CACHE_KEY = 'bdc_report_users.json';

    private const SOURCE_URL = 'https://api.p2k.co.id/bdc-marketing/report-users';

    private const MAX_AGE_SECONDS = 900;

    /**
     * Memoized for the lifetime of the request: regionalTotals() calls
     * snapshot() once per regional (4x on the Dashboard), and a failed live
     * fetch doesn't touch the cache's mtime — without this, a slow/down
     * api.p2k.co.id would eat a full timeout() on every one of those calls
     * instead of just the first.
     */
    private static ?array $requestSnapshot = null;

    public static function snapshot(): array
    {
        if (self::$requestSnapshot !== null) {
            return self::$requestSnapshot;
        }

        $cached = self::cacheRead();
        if ($cached !== [] && self::cacheAge() <= self::MAX_AGE_SECONDS) {
            $cached['source_mode'] = 'cache';

            return self::$requestSnapshot = $cached;
        }

        return self::$requestSnapshot = self::fetch($cached);
    }

    public static function refresh(): array
    {
        return self::fetch(self::cacheRead());
    }

    private static function fetch(array $fallback): array
    {
        try {
            $response = Http::timeout(20)->acceptJson()
                ->withHeaders(['User-Agent' => 'RegionalB-Dashboard/1.0'])
                ->get(self::SOURCE_URL);
        } catch (\Throwable) {
            $response = null;
        }

        $decoded = $response?->successful() ? $response->json() : null;
        if (! is_array($decoded) || ! isset($decoded['listdata']) || ! is_array($decoded['listdata'])) {
            if ($fallback !== []) {
                $fallback['source_mode'] = 'stale_cache';
                $fallback['source_error'] = 'API tidak terbaca, memakai cache terakhir.';

                return $fallback;
            }

            return [
                'kode' => '',
                'message' => 'API tidak terbaca.',
                'source_url' => self::SOURCE_URL,
                'source_mode' => 'unreadable',
                'fetched_at' => now()->toDateTimeString(),
                'listdata' => [],
            ];
        }

        $decoded['source_url'] = self::SOURCE_URL;
        $decoded['source_mode'] = 'live_api';
        $decoded['fetched_at'] = now()->toDateTimeString();
        self::cacheWrite($decoded);
        self::storeSnapshotRows((array) $decoded['listdata'], (string) $decoded['fetched_at']);

        return $decoded;
    }

    private static function storeSnapshotRows(array $rows, string $fetchedAt): void
    {
        if ($rows === []) {
            return;
        }

        $snapshotDate = substr($fetchedAt !== '' ? $fetchedAt : now()->toDateTimeString(), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $snapshotDate)) {
            $snapshotDate = now()->toDateString();
        }

        $allowedRegionals = array_flip(AreaRegionals::forArea('Regional B'));
        $now = now();
        $records = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $wilayah = trim((string) ($row['wilayah'] ?? ''));
            if (! isset($allowedRegionals[$wilayah])) {
                continue;
            }
            $name = trim((string) ($row['nama'] ?? $row['name'] ?? ''));
            $nik = trim((string) ($row['nik'] ?? ''));
            if ($name === '' && $nik === '') {
                continue;
            }
            if ($nik === '') {
                $nik = self::usernameFromName($name);
            }

            $records[] = [
                'snapshot_date' => $snapshotDate,
                'fetched_at' => $fetchedAt !== '' ? $fetchedAt : $now->toDateTimeString(),
                'nik' => $nik,
                'name' => $name !== '' ? $name : $nik,
                'campus_name' => trim((string) ($row['kampus'] ?? $row['campus_name'] ?? '')) ?: null,
                'wilayah' => $wilayah !== '' ? $wilayah : null,
                'total_count' => (int) self::numberValue($row['total'] ?? 0),
                'data_baru_count' => (int) self::numberValue($row['data_baru'] ?? $row['data_baru_count'] ?? 0),
                'cold_count' => (int) self::numberValue($row['cold'] ?? 0),
                'warm_count' => (int) self::numberValue($row['warm'] ?? 0),
                'hot_count' => (int) self::numberValue($row['hot'] ?? 0),
                'closing_count' => (int) self::numberValue($row['closing'] ?? 0),
                'wawancara_count' => (int) self::numberValue($row['wawancara'] ?? 0),
                'belum_herreg_count' => (int) self::numberValue($row['belum_herreg'] ?? 0),
                'herreg_count' => (int) self::numberValue($row['herreg'] ?? 0),
                'fu_hari_ini_count' => (int) self::numberValue($row['fu_hari_ini'] ?? 0),
                'raw_payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($records === []) {
            return;
        }

        DB::table('rsm_bdc_report_user_snapshots')->upsert(
            $records,
            ['snapshot_date', 'nik'],
            ['fetched_at', 'name', 'campus_name', 'wilayah', 'total_count', 'data_baru_count', 'cold_count', 'warm_count', 'hot_count', 'closing_count', 'wawancara_count', 'belum_herreg_count', 'herreg_count', 'fu_hari_ini_count', 'raw_payload', 'updated_at']
        );
    }

    /**
     * Sums the /bdc-users report columns (Total, Baru, FU Hari Ini, Closing,
     * Wawancara, Belum Herreg) across every staff row whose wilayah matches
     * the given regional (and, if given, whose kampus matches $campus —
     * fuzzy via CampusMatcher, same as CollabMetricsService::campusTotals()'s
     * staff auto-scope, since the API's campus spelling doesn't always
     * agree with rsm_users.campus_name). Used by the Dashboard's
     * per-regional recap card.
     *
     * @return array{total: float, data_baru: float, fu_hari_ini: float, closing: float, wawancara: float, belum_herreg: float}
     */
    public static function regionalTotals(string $regional, ?string $campus = null): array
    {
        $campus = trim((string) $campus);
        $rows = array_filter(
            (array) (self::snapshot()['listdata'] ?? []),
            function ($row) use ($regional, $campus) {
                if (! is_array($row) || (string) ($row['wilayah'] ?? '') !== $regional) {
                    return false;
                }
                if ($campus === '') {
                    return true;
                }

                return CampusMatcher::matches((string) ($row['kampus'] ?? $row['campus_name'] ?? ''), $campus);
            }
        );

        $sum = fn (string $key): float => array_sum(array_map(fn ($row) => self::numberValue($row[$key] ?? 0), $rows));

        return [
            'total' => $sum('total'),
            'data_baru' => $sum('data_baru'),
            'fu_hari_ini' => $sum('fu_hari_ini'),
            'closing' => $sum('closing'),
            'wawancara' => $sum('wawancara'),
            'belum_herreg' => $sum('belum_herreg'),
        ];
    }

    private static function usernameFromName(string $name): string
    {
        $username = preg_replace('/[^a-z0-9]+/i', '', strtolower(trim($name))) ?: '';

        return $username !== '' ? $username : 'bdcuser';
    }

    private static function numberValue(mixed $value): float
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private static function cacheAge(): int
    {
        return Storage::disk('local')->exists(self::CACHE_KEY)
            ? time() - Storage::disk('local')->lastModified(self::CACHE_KEY)
            : PHP_INT_MAX;
    }

    private static function cacheRead(): array
    {
        if (Storage::disk('local')->exists(self::CACHE_KEY)) {
            $decoded = json_decode((string) Storage::disk('local')->get(self::CACHE_KEY), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $legacy = base_path('../regionalb.online/public_html/runtime/cache/bdc_report_users.json');
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
