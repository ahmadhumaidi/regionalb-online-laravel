<?php

namespace App\Services\Dashboard;

use App\Models\RsmUser;

/**
 * Cross-references collab-sourced staff_name/staff_nik (from
 * rsm_collab_daily_metrics, e.g. "Closing Personal Per Regional") against
 * rsm_users by NIK first, then name; the two systems are not linked by a
 * foreign key, only by these loosely-matching text fields. Shared by
 * AchievementReportService (the "Laporan Pencapaian" panel on Rekap) and
 * AchievementController (/pencapaian) so both surfaces resolve a collab row
 * to the same rsm_users record instead of drifting apart.
 */
class StaffProfileMatcher
{
    /**
     * The "Closing Personal Per Regional" source appends a "Tim Terpilih"
     * suffix to some staff_name values (a cohort tag from cb.web.id, not
     * part of the person's actual name). Strip it before it reaches
     * display, self-matching, or the rsm_users name lookup.
     */
    public static function cleanName(string $name): string
    {
        $name = trim($name);
        $stripped = preg_replace('/\s+Tim Terpilih$/i', '', $name);

        return trim($stripped ?? $name);
    }

    /**
     * Active rsm_users in the area, keyed by lowercased nik and by
     * lowercased name, for O(1) lookup while walking collab rows.
     *
     * @return array{byNik: array<string, RsmUser>, byName: array<string, RsmUser>}
     */
    public static function index(string $area): array
    {
        $byNik = [];
        $byName = [];

        foreach (RsmUser::query()->where('area', $area)->where('is_active', true)->get() as $candidate) {
            $nameKey = mb_strtolower(trim($candidate->name));
            $nikKey = mb_strtolower(trim((string) $candidate->nik));
            if ($nameKey !== '') {
                $byName[$nameKey] = $candidate;
            }
            if ($nikKey !== '') {
                $byNik[$nikKey] = $candidate;
            }
        }

        return ['byNik' => $byNik, 'byName' => $byName];
    }

    /** @param array{byNik: array<string, RsmUser>, byName: array<string, RsmUser>} $index */
    public static function find(array $index, string $name, string $nik): ?RsmUser
    {
        $nikKey = mb_strtolower(trim($nik));
        if ($nikKey !== '' && isset($index['byNik'][$nikKey])) {
            return $index['byNik'][$nikKey];
        }

        $nameKey = mb_strtolower(trim($name));

        return $index['byName'][$nameKey] ?? null;
    }
}
