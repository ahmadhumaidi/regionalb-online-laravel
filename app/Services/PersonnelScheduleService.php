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
