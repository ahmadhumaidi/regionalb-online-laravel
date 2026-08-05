<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
class PersonnelScheduleService
{
    private const URL = 'https://cb.web.id/media.php?p=jadwal&zona=2';
    public static function snapshot(): array
    {
        if (Storage::disk('local')->exists('jadwal_personalia.json')) return json_decode(Storage::disk('local')->get('jadwal_personalia.json'), true) ?: [];
        $legacy = base_path('../regionalb.online/public_html/runtime/cache/jadwal_koordinator_cb.json');
        return is_file($legacy) ? (json_decode((string) file_get_contents($legacy), true) ?: []) : [];
    }
    public static function sync(): array
    {
        $existing = self::snapshot();
        try {
            $response = Http::timeout(20)->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])->get(self::URL);
            preg_match('/<table[^>]*id="tb_jadwal"[^>]*>(.*?)<\/table>/is', $response->successful() ? $response->body() : '', $match);
            $inner = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $match[1] ?? '') ?? '';
            $inner = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $inner) ?? $inner;
            if (trim($inner) === '') throw new \RuntimeException('Tabel jadwal tidak ditemukan pada sumber.');
            $result = ['synced_at' => now()->format('Y-m-d H:i:s'), 'zonas' => [2 => ['zona' => 2, 'source_url' => self::URL, 'table_html' => '<table class="collab-raw-table">'.$inner.'</table>', 'fetched_at' => now()->format('Y-m-d H:i:s')],], 'errors' => []];
            Storage::disk('local')->put('jadwal_personalia.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            return $result;
        } catch (\Throwable $e) {
            if ($existing !== []) { $existing['last_sync_errors'] = [$e->getMessage()]; Storage::disk('local')->put('jadwal_personalia.json', json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)); }
            return $existing + ['errors' => [$e->getMessage()]];
        }
    }
}
