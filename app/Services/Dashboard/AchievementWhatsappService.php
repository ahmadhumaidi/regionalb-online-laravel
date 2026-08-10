<?php

namespace App\Services\Dashboard;

use App\Models\RsmUser;
use Illuminate\Support\Facades\Storage;

/**
 * Ports the "Bahan WhatsApp Otomatis" text generator on the Rekap page
 * (rsm_achievement_whatsapp_text, rsm_db.php:6191-6403), reusing the same
 * regional/unit grouping as the "Laporan Pencapaian" panel
 * (AchievementReportService). The accompanying image is captured
 * client-side from that panel (see resources/js/app.js
 * captureAchievementSnapshot) rather than server-rendered — a headless-
 * Chromium screenshot was attempted first but is blocked by snap
 * confinement on this VPS regardless of invoking user/context.
 */
class AchievementWhatsappService
{
    private const CACHE_KEY = 'achievement_whatsapp_latest.json';

    private const MONTHS_ID = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function generate(string $area, array $filters, RsmUser $actor): array
    {
        $payload = AchievementReportService::build($area, $filters, $actor);
        $generatedAt = now('Asia/Jakarta');
        $text = self::buildText($filters['date_from'], $filters['date_to'], $generatedAt, $payload);

        $artifact = [
            'text' => $text,
            'generated_at' => $generatedAt->format('Y-m-d H:i:s'),
            'period' => ['date_from' => $filters['date_from'], 'date_to' => $filters['date_to']],
        ];
        Storage::disk('local')->put(self::CACHE_KEY, json_encode($artifact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $artifact;
    }

    public static function latest(): ?array
    {
        if (! Storage::disk('local')->exists(self::CACHE_KEY)) {
            return null;
        }
        $decoded = json_decode(Storage::disk('local')->get(self::CACHE_KEY), true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function buildText(string $dateFrom, string $dateTo, \Illuminate\Support\Carbon $generatedAt, array $payload): string
    {
        $periodText = $dateFrom === $dateTo
            ? self::formatDateLong($dateFrom)
            : self::formatDateLong($dateFrom).' s/d '.self::formatDateLong($dateTo);

        $lines = ['*Laporan Pencapaian Regional B*'];
        $lines[] = 'Periode : '.$periodText;
        $lines[] = 'Sinkron : '.$generatedAt->format('H:i:s');
        $lines[] = '___________________________________________';
        $lines[] = '';
        $leader = $payload['leader'];
        $lines[] = '*'.$leader['label'].': '.$leader['name'].' - '.number_format($leader['registrasi'], 0, ',', '.').' closing*';
        $lines[] = '';

        foreach ($payload['regionals'] as $regional) {
            $lines[] = '*'.$regional['regional'].' - '.$regional['korwil_name'].' = '.number_format($regional['registrasi'], 0, ',', '.').'*';
            if ($regional['units'] === []) {
                $lines[] = '- Belum ada unit closing';
            }
            foreach ($regional['units'] as $unit) {
                $lines[] = '> - '.$unit['unit'].': '.number_format($unit['registrasi'], 0, ',', '.');
                if (! empty($unit['campus_breakdown'])) {
                    $parts = array_map(fn (array $b) => $b['label'].': '.number_format($b['total'], 0, ',', '.'), $unit['campus_breakdown']);
                    $lines[] = '  ('.implode(' / ', $parts).')';
                }
                foreach ($unit['staff'] as $staff) {
                    $lines[] = '- '.$staff['name'].': '.number_format($staff['registrasi'], 0, ',', '.').' closing staff';
                }
                if (($unit['non_staff_registrasi'] ?? 0) > 0) {
                    $lines[] = '- CS: '.number_format($unit['non_staff_registrasi'], 0, ',', '.').' closing';
                }
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private static function formatDateLong(string $date): string
    {
        $timestamp = strtotime($date);
        if (! $timestamp) {
            return $date;
        }

        return date('j', $timestamp).' '.(self::MONTHS_ID[(int) date('n', $timestamp)] ?? date('F', $timestamp)).' '.date('Y', $timestamp);
    }
}
