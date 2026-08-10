<?php

namespace App\Services\Dashboard;

use App\Models\RsmUser;
use App\Support\CampusMatcher;
use Illuminate\Support\Collection;

/**
 * "Scoring" menu: one wide table collecting every assessment indicator
 * already tracked elsewhere in the system (personal registrasi/herreg,
 * campus registrasi/herreg, ad reports, follow ups, leads, report volume)
 * into a single row per staff member - no point weighting yet, that's a
 * separate follow-up (per Ahmad Humaidi: build the table first, decide
 * indicator weights afterward in a dedicated config screen).
 */
class ScoringTableService
{
    /** @return array{rows: list<array>, synced_at: ?string} */
    public static function build(string $area, array $filters, RsmUser $user): array
    {
        $indicatorRows = GamificationService::indicatorRows($area, $filters, $user);

        $campusRegistrasi = self::campusIndex(CollabMetricsService::campusTotals($filters, $area, $user, 'Closing Kampus Regional'));
        $campusHerreg = self::campusIndex(CollabMetricsService::campusTotals($filters, $area, $user, 'Herreg Kampus Regional'));

        $rows = $indicatorRows
            ->filter(fn (array $row) => trim($row['name']) !== '' && $row['name'] !== '-')
            ->map(function (array $row) use ($campusRegistrasi, $campusHerreg) {
                $unitName = (string) ($row['unit_name'] ?? '');

                return [
                    'name' => $row['name'],
                    'wilayah' => $row['wilayah'] ?: '-',
                    'unit_name' => $unitName ?: '-',
                    'registrasi_personal' => (float) $row['closing_for_points'],
                    'herregistrasi_personal' => (float) $row['herreg_for_points'],
                    'registrasi_kampus' => self::lookupCampus($unitName, $campusRegistrasi),
                    'herregistrasi_kampus' => self::lookupCampus($unitName, $campusHerreg),
                    'laporan_iklan' => (int) $row['uploaded_ad_reports'],
                    'realisasi_iklan' => (float) $row['spend_total'],
                    'follow_up_total' => (int) $row['follow_up_total'],
                    'leads_total' => (int) $row['leads_total'],
                    'laporan_total' => (int) $row['report_total'],
                    'hari_aktif' => (int) $row['report_days'],
                ];
            })
            ->sortBy([['wilayah', 'asc'], ['name', 'asc']])
            ->values();

        return [
            'rows' => $rows->all(),
            'synced_at' => CollabMetricsService::syncedAt(),
        ];
    }

    /** @param array{rows: array} $campusTotals @return array<string, float> keyed by lowercased campus label */
    private static function campusIndex(array $campusTotals): array
    {
        return collect($campusTotals['rows'])
            ->groupBy(fn (array $row) => mb_strtolower(trim((string) $row['unit'])))
            ->map(fn (Collection $group) => (float) $group->sum('registrasi'))
            ->all();
    }

    /** @param array<string, float> $index */
    private static function lookupCampus(string $unitName, array $index): float
    {
        if (trim($unitName) === '') {
            return 0.0;
        }

        $direct = $index[mb_strtolower(trim($unitName))] ?? null;
        if ($direct !== null) {
            return $direct;
        }

        foreach ($index as $campusLabel => $value) {
            if (CampusMatcher::matches($unitName, $campusLabel)) {
                return $value;
            }
        }

        return 0.0;
    }
}
