<?php

namespace App\Services\Dashboard;

use App\Models\RsmUser;
use App\Support\AreaRegionals;
use Illuminate\Support\Facades\Storage;

/**
 * Ports the "Bahan WhatsApp Otomatis" generator on the Rekap page
 * (rsm_generate_achievement_whatsapp_artifact/rsm_achievement_whatsapp_text,
 * rsm_db.php:6191-6403). Text-only, matching the already-established pattern
 * for the coordinator schedule WhatsApp report — the legacy image rendering
 * (headless-browser screenshot with a GD fallback) is out of scope.
 */
class AchievementWhatsappService
{
    private const CACHE_KEY = 'achievement_whatsapp_latest.json';

    private const MONTHS_ID = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function generate(string $area, RsmUser $actor): array
    {
        $today = now('Asia/Jakarta')->toDateString();
        $filters = ['date_from' => $today, 'date_to' => $today, 'wilayah' => '', 'unit_name' => '', 'staff_name' => ''];

        $performance = CollabMetricsService::staffPerformance($area, $filters, $actor);

        $usersByName = RsmUser::query()->where('is_active', true)
            ->get(['name', 'regional', 'campus_name'])
            ->keyBy(fn (RsmUser $user) => mb_strtolower(trim($user->name)));

        $regionalUnits = [];
        foreach ($performance['rows'] as $row) {
            if ((float) $row['registrasi'] <= 0) {
                continue;
            }
            $user = $usersByName->get(mb_strtolower(trim((string) $row['name'])));
            $regional = $row['regional'] ?: ($user->regional ?? 'Tanpa Regional');
            $unit = trim((string) ($user->campus_name ?? '')) ?: 'Unit belum diatur';

            $regionalUnits[$regional][$unit]['unit'] ??= $unit;
            $regionalUnits[$regional][$unit]['registrasi'] = ($regionalUnits[$regional][$unit]['registrasi'] ?? 0) + $row['registrasi'];
            $regionalUnits[$regional][$unit]['staff'][] = ['name' => $row['name'], 'registrasi' => $row['registrasi']];
        }

        $koordinators = RsmUser::query()->where('role', 'koordinator')->where('is_active', true)
            ->get(['name', 'regional'])->keyBy('regional');

        $regionalCards = [];
        $visibleTotal = 0.0;
        foreach (AreaRegionals::forArea($area) as $regional) {
            $units = collect($regionalUnits[$regional] ?? [])
                ->map(function (array $unit) {
                    usort($unit['staff'], fn (array $a, array $b) => $b['registrasi'] <=> $a['registrasi']);

                    return $unit;
                })
                ->sortByDesc('registrasi')
                ->values()
                ->all();

            $regionalTotal = array_sum(array_column($units, 'registrasi'));
            $visibleTotal += $regionalTotal;

            $regionalCards[] = [
                'regional' => $regional,
                'registrasi' => $regionalTotal,
                'korwil_name' => $koordinators->get($regional)?->name ?: 'Korwil belum diatur',
                'units' => $units,
            ];
        }

        $leaderLabel = match ($actor->role) {
            'staff' => 'Staff',
            'koordinator' => 'Korwil',
            default => 'Senior Manager',
        };

        $text = self::buildText($today, $leaderLabel, $actor->name, $visibleTotal, $regionalCards);

        $artifact = [
            'text' => $text,
            'generated_at' => now()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
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

    private static function buildText(string $date, string $leaderLabel, string $leaderName, float $total, array $regionalCards): string
    {
        $lines = ['*Laporan Pencapaian Regional B*'];
        $lines[] = 'Periode : '.self::formatDateLong($date);
        $lines[] = 'Sinkron : '.now()->timezone('Asia/Jakarta')->format('H:i:s');
        $lines[] = '___________________________________________';
        $lines[] = '';
        $lines[] = '*'.$leaderLabel.': '.$leaderName.' - '.number_format($total, 0, ',', '.').' closing*';
        $lines[] = '';

        foreach ($regionalCards as $regional) {
            $lines[] = '*'.$regional['regional'].' - '.$regional['korwil_name'].' = '.number_format($regional['registrasi'], 0, ',', '.').'*';
            if ($regional['units'] === []) {
                $lines[] = '- Belum ada unit closing';
            }
            foreach ($regional['units'] as $unit) {
                $lines[] = '> - '.$unit['unit'].': '.number_format($unit['registrasi'], 0, ',', '.');
                foreach ($unit['staff'] as $staff) {
                    $lines[] = '- '.$staff['name'].': '.number_format($staff['registrasi'], 0, ',', '.').' closing staff';
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
