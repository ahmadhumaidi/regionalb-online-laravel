<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * "Jadwal Personalia" (cb.web.id, zona=2) — ports rsm_jadwal_fetch_zona()/
 * rsm_jadwal_sync_cache()/rsm_jadwal_extract_table_html() (rsm_db.php:
 * 5238-5335, live production copy — the git-mirrored source was stale and
 * missing this feature entirely). The source requires an authenticated
 * session (rsm_collab_authenticated_html()), same as CollabSourceService's
 * pencapaian sync — reuses CollabSourceService::authenticatedHtml() rather
 * than a plain unauthenticated request.
 */
class PersonnelScheduleService
{
    private const OFF_CODES = ['L', 'LN'];

    private const URL = 'https://cb.web.id/media.php?p=jadwal&zona=2';

    private const TABLE_ID = 'tb_jadwal';

    public static function snapshot(): array
    {
        if (Storage::disk('local')->exists('jadwal_personalia.json')) {
            return json_decode(Storage::disk('local')->get('jadwal_personalia.json'), true) ?: [];
        }
        $legacy = base_path('../regionalb.online/public_html/runtime/cache/jadwal_koordinator_cb.json');

        return is_file($legacy) ? (json_decode((string) file_get_contents($legacy), true) ?: []) : [];
    }

    public static function sync(): array
    {
        $existing = self::snapshot();

        try {
            $html = CollabSourceService::authenticatedHtml(self::URL);
            if (trim($html) === '') {
                throw new \RuntimeException('Source tidak terbaca (login/koneksi gagal).');
            }

            $tableHtml = self::extractTableHtml($html, self::TABLE_ID);
            if ($tableHtml === '') {
                throw new \RuntimeException('Tabel jadwal tidak ditemukan pada halaman sumber.');
            }

            $result = [
                'synced_at' => now()->format('Y-m-d H:i:s'),
                'zonas' => [
                    2 => [
                        'zona' => 2,
                        'source_url' => self::URL,
                        'table_html' => $tableHtml,
                        'fetched_at' => now()->format('Y-m-d H:i:s'),
                    ],
                ],
                'errors' => [],
            ];
            Storage::disk('local')->put('jadwal_personalia.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

            return $result;
        } catch (\Throwable $e) {
            if ($existing !== []) {
                $existing['last_sync_errors'] = [$e->getMessage()];
                Storage::disk('local')->put('jadwal_personalia.json', json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            }

            return $existing + ['errors' => [$e->getMessage()]];
        }
    }

    /** @return array<string, string> Normalized staff name => off-duty code. */
    public static function offStatusesForDate(string $date): array
    {
        $snapshot = self::snapshot();
        $zone = $snapshot['zonas'][2] ?? [];
        $tableHtml = (string) ($zone['table_html'] ?? '');
        $fetchedAt = (string) ($zone['fetched_at'] ?? '');

        if ($tableHtml === '' || substr($fetchedAt, 0, 7) !== substr($date, 0, 7)) {
            return [];
        }

        $day = (int) substr($date, 8, 2);
        if ($day < 1 || $day > 31 || ! preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tableHtml, $rows)) {
            return [];
        }

        $statuses = [];
        foreach ($rows[1] as $row) {
            if (! preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $row, $cells)) {
                continue;
            }

            $values = array_map(
                static fn (string $cell): string => trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''),
                $cells[1],
            );
            if (count($values) < 36 || ! preg_match('/^SG\./i', $values[0])) {
                continue;
            }

            $code = mb_strtoupper(trim($values[4 + $day] ?? ''));
            if (in_array($code, self::OFF_CODES, true)) {
                $statuses[self::nameKey($values[1] ?? '')] = $code;
            }
        }

        return $statuses;
    }

    private static function nameKey(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+Tim Terpilih$/i', '', $name)));
    }

    /** Port of rsm_jadwal_extract_table_html() (rsm_db.php:5269-5279). */
    private static function extractTableHtml(string $html, string $tableId): string
    {
        if (! preg_match('/<table[^>]*id="'.preg_quote($tableId, '/').'"[^>]*>(.*?)<\/table>/is', $html, $m)) {
            return '';
        }
        $inner = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $m[1]) ?? $m[1];
        $inner = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $inner) ?? $inner;
        $inner = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $inner) ?? $inner;

        return '<table class="collab-raw-table">'.$inner.'</table>';
    }
}
