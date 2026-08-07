<?php

namespace App\Services;

/**
 * Cross-references the "Jadwal Personalia" Collab snapshot (staff duty
 * roster, zona=2) against the 4 regional koordinator to find days they're
 * marked off ('L'/'LN' codes) — used by CoordinatorScheduleController to
 * flag coordinator-schedule rows that land on a koordinator's day off.
 * Ports rsm_jadwal_parse_zona_rows()/rsm_jadwal_koordinator_name_map()/
 * rsm_jadwal_koordinator_libur_map() (rsm_db.php:5340-5401, live production
 * copy).
 */
class CoordinatorLiburService
{
    /** Local koordinator name => name as it appears in the Collab roster. */
    private const NAME_MAP = [
        'Hamaruddin' => 'Hamaruddin',
        'Kundi Harto' => 'Kundi Harto',
        'M. Nor Abidin' => 'Moh Nor Abidin',
        'Nugroho Budi Santoso' => 'Nugroho Budi Santoso',
    ];

    private const OFF_CODES = ['L', 'LN'];

    /** @return array{month: string, coordinators: array<string, array<int, string>>} */
    public static function map(): array
    {
        $snapshot = PersonnelScheduleService::snapshot();
        $tableHtml = (string) ($snapshot['zonas'][2]['table_html'] ?? '');
        $fetchedAt = (string) ($snapshot['zonas'][2]['fetched_at'] ?? '');
        if ($tableHtml === '' || $fetchedAt === '') {
            return ['month' => '', 'coordinators' => []];
        }

        $parsedRows = self::parseZonaRows($tableHtml);
        $coordinators = [];
        foreach (self::NAME_MAP as $localName => $cbName) {
            foreach ($parsedRows as $parsedRow) {
                if (mb_strtolower(trim($parsedRow['nama'])) !== mb_strtolower(trim($cbName))) {
                    continue;
                }
                $liburDays = [];
                foreach ($parsedRow['days'] as $day => $code) {
                    $codeNorm = mb_strtoupper(trim($code));
                    if (in_array($codeNorm, self::OFF_CODES, true)) {
                        $liburDays[$day] = $codeNorm;
                    }
                }
                $coordinators[$localName] = $liburDays;
                break;
            }
        }

        return ['month' => substr($fetchedAt, 0, 7), 'coordinators' => $coordinators];
    }

    /** Port of rsm_jadwal_parse_zona_rows() (rsm_db.php:5350-5372). */
    private static function parseZonaRows(string $tableHtml): array
    {
        if (! preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tableHtml, $trMatches)) {
            return [];
        }

        $rows = [];
        foreach ($trMatches[1] as $trInner) {
            if (! preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $trInner, $tdMatches)) {
                continue;
            }
            $cells = array_map(
                static fn (string $cell): string => trim(preg_replace('/\s+/', ' ', strip_tags($cell)) ?? ''),
                $tdMatches[1]
            );
            if (count($cells) < 36 || ! preg_match('/^SG\./i', $cells[0])) {
                continue;
            }
            $days = [];
            for ($day = 1; $day <= 31; $day++) {
                $days[$day] = $cells[4 + $day] ?? '';
            }
            $rows[] = ['nik' => $cells[0], 'nama' => $cells[1], 'days' => $days];
        }

        return $rows;
    }
}
