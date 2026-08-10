<?php

namespace App\Services\Dashboard;

use App\Models\RsmUser;
use App\Support\AreaRegionals;

/**
 * "Laporan Pencapaian" panel on the Rekap page — ports
 * render_achievement_report() (production dashboard.php:2660), but sourced
 * from "Closing Personal Per Regional" / "Herreg Personal Per Regional" via
 * CollabMetricsService::personalPerformance() instead of the deprecated
 * "Closing Collab"/"Herreg Collab" pairing the legacy version reads.
 *
 * Per-unit totals are reconciled against "Closing Kampus Regional"
 * (CollabMetricsService::campusTotals): the campus source is the ground
 * truth for a unit's real total, since not every closing gets attributed to
 * a specific staff member. The gap (campus total minus the sum of that
 * unit's staff rows) is surfaced as "non-staff" — matched by name, not
 * report_name, since the two sources spell the same campus differently
 * (e.g. rsm_users has "Universitas Patria Artha ( UPA )" where the campus
 * source has plain "Universitas Patria Artha"; "UUI" vs "Universitas
 * Ubudiyah Indonesia (UUI)").
 */
class AchievementReportService
{
    private const SENIOR_ROLES = ['super_user', 'executive_director', 'director', 'senior'];

    /**
     * Campus labels that should collapse into one unit card even though
     * they don't share a substring/parenthetical relation, keyed by
     * lowercased source label, valued by the display label of the target
     * they merge into:
     *  - "same real campus, spelled differently" (found by diffing every
     *    distinct rsm_users.campus_name against every distinct
     *    "Closing Kampus Regional" campus_name, 2026-08-10)
     *  - "different campuses administratively reported together" (one
     *    staff member covers both — confirmed by user, 2026-08-10:
     *    Ahmad Wirda Burhanudin Mubarroq handles both IKIP and STIE
     *    Widya Darma)
     */
    private const CAMPUS_ALIASES = [
        'sekolah tinggi bahasa asing lia yogyakarta (stba lia)' => 'STBA Lia Yogyakarta',
        'institut teknologi dan bisnis stikom bali' => 'ITB STIKOM Bali',
        'ikip widya darma' => 'IKIP Widya Darma / STIE Widya Darma',
        'stie widya darma' => 'IKIP Widya Darma / STIE Widya Darma',
    ];

    public static function build(string $area, array $filters, RsmUser $user): array
    {
        $performance = CollabMetricsService::personalPerformance($area, $filters, $user);
        $reconcileCampus = $user->role !== 'staff';
        $campusRows = $reconcileCampus ? self::indexCampusRows(CollabMetricsService::campusTotals($filters, $area, $user)) : [];

        $scopedRegionals = in_array($user->role, ['koordinator', 'staff'], true) && trim((string) $user->regional) !== ''
            ? [$user->regional]
            : AreaRegionals::forArea($area);
        $scopedLookup = array_fill_keys(array_map(fn (string $r) => mb_strtolower($r), $scopedRegionals), true);

        $authName = mb_strtolower(trim($user->name));
        $authNik = mb_strtolower(trim((string) $user->nik));

        $usersByName = [];
        $usersByNik = [];
        $senior = null;
        $coordinatorsByRegional = [];

        foreach (RsmUser::query()->where('area', $area)->where('is_active', true)->get() as $candidate) {
            $candidateRegional = trim((string) $candidate->regional);
            if (in_array($candidate->role, ['koordinator', 'staff'], true) && $scopedLookup !== [] && $candidateRegional !== '' && ! isset($scopedLookup[mb_strtolower($candidateRegional)])) {
                continue;
            }
            if ($user->role === 'staff') {
                $candName = mb_strtolower(trim($candidate->name));
                $candNik = mb_strtolower(trim((string) $candidate->nik));
                $isSelf = $candName === $authName || ($authNik !== '' && $candNik === $authNik);
                if (! in_array($candidate->role, [...self::SENIOR_ROLES, 'mentor'], true) && ! $isSelf) {
                    continue;
                }
            }

            $nameKey = mb_strtolower(trim($candidate->name));
            $nikKey = mb_strtolower(trim((string) $candidate->nik));
            if ($nameKey !== '') {
                $usersByName[$nameKey] = $candidate;
            }
            if ($nikKey !== '') {
                $usersByNik[$nikKey] = $candidate;
            }
            if (in_array($candidate->role, self::SENIOR_ROLES, true) && ! $senior) {
                $senior = $candidate;
            }
            if ($candidate->role === 'koordinator') {
                $regionalKey = $candidateRegional ?: 'Tanpa Regional';
                $coordinatorsByRegional[$regionalKey] ??= $candidate;
            }
        }

        $regionalUnits = [];
        $consumedCampusRows = [];
        foreach ($performance['rows'] as $row) {
            $registrasi = (float) $row['registrasi'];
            if ($registrasi <= 0) {
                continue;
            }
            $name = self::cleanStaffName((string) $row['name']);
            $nik = trim((string) ($row['nik'] ?? ''));
            if ($user->role === 'staff') {
                $rowName = mb_strtolower($name);
                $rowNik = mb_strtolower($nik);
                $isSelf = $rowName === $authName || ($authNik !== '' && $rowNik === $authNik);
                if (! $isSelf) {
                    continue;
                }
            }
            $matchedUser = $usersByNik[mb_strtolower($nik)] ?? $usersByName[mb_strtolower($name)] ?? null;
            $regional = (string) ($row['regional'] ?: ($matchedUser->regional ?? 'Tanpa Regional'));
            if ($scopedLookup !== [] && ! isset($scopedLookup[mb_strtolower($regional)])) {
                continue;
            }
            $rawUnit = trim((string) ($matchedUser->campus_name ?? ''));

            $campusMatch = $rawUnit !== '' ? self::matchCampusRow($campusRows, $regional, $rawUnit) : null;
            $unit = $campusMatch ? $campusMatch['label'] : ($rawUnit ?: 'Unit belum diatur');
            if ($campusMatch !== null) {
                $consumedCampusRows[$campusMatch['index']] = true;
            }

            $regionalUnits[$regional][$unit]['unit'] ??= $unit;
            $regionalUnits[$regional][$unit]['campus_total'] ??= $campusMatch['total'] ?? null;
            $regionalUnits[$regional][$unit]['campus_breakdown'] ??= $campusMatch['breakdown'] ?? null;
            $regionalUnits[$regional][$unit]['staff_registrasi'] = ($regionalUnits[$regional][$unit]['staff_registrasi'] ?? 0) + $registrasi;
            $regionalUnits[$regional][$unit]['herregistrasi'] = ($regionalUnits[$regional][$unit]['herregistrasi'] ?? 0) + (float) $row['herregistrasi'];
            $regionalUnits[$regional][$unit]['staff'][] = [
                'name' => $name,
                'photo' => $matchedUser?->photoUrl(),
                'registrasi' => $registrasi,
                'herregistrasi' => (float) $row['herregistrasi'],
            ];
        }

        // Campus-source units with a real total but no staff closing at all
        // (fully unattributed to any staff member) still need to show up.
        if ($reconcileCampus) {
            foreach ($campusRows as $index => $campusRow) {
                if (isset($consumedCampusRows[$index])) {
                    continue;
                }
                $regional = $campusRow['regional'];
                if ($scopedLookup !== [] && ! isset($scopedLookup[mb_strtolower($regional)])) {
                    continue;
                }
                $unit = $campusRow['label'];
                $regionalUnits[$regional][$unit]['unit'] ??= $unit;
                $regionalUnits[$regional][$unit]['campus_total'] ??= $campusRow['total'];
                $regionalUnits[$regional][$unit]['campus_breakdown'] ??= $campusRow['breakdown'];
                $regionalUnits[$regional][$unit]['staff_registrasi'] ??= 0.0;
                $regionalUnits[$regional][$unit]['herregistrasi'] ??= 0.0;
                $regionalUnits[$regional][$unit]['staff'] ??= [];
            }
        }

        $regionals = $scopedRegionals !== [] ? $scopedRegionals : array_keys($regionalUnits);

        [$leader, $leaderLabel] = match ($user->role) {
            'koordinator' => [$user, 'Korwil'],
            'staff' => [$user, 'Staff'],
            default => [$senior ?: $user, 'Senior Manager'],
        };

        $regionalCards = [];
        $visibleTotal = 0.0;
        foreach ($regionals as $regional) {
            $units = collect($regionalUnits[$regional] ?? [])
                ->map(function (array $unit) {
                    usort($unit['staff'], fn (array $a, array $b) => $b['registrasi'] <=> $a['registrasi']);
                    $staffTotal = (float) $unit['staff_registrasi'];
                    $total = $unit['campus_total'] !== null ? max((float) $unit['campus_total'], $staffTotal) : $staffTotal;

                    $breakdown = $unit['campus_breakdown'] ?? null;

                    return [
                        'unit' => $unit['unit'],
                        'registrasi' => $total,
                        'staff_registrasi' => $staffTotal,
                        'non_staff_registrasi' => max(0.0, $total - $staffTotal),
                        'herregistrasi' => $unit['herregistrasi'],
                        'staff' => $unit['staff'],
                        'campus_breakdown' => is_array($breakdown) && count($breakdown) > 1 ? $breakdown : null,
                    ];
                })
                ->sortByDesc('registrasi')
                ->values()
                ->all();
            $regionalTotal = array_sum(array_column($units, 'registrasi'));
            $visibleTotal += $regionalTotal;
            $coordinator = $coordinatorsByRegional[$regional] ?? null;

            $regionalCards[] = [
                'regional' => $regional,
                'registrasi' => $regionalTotal,
                'korwil_name' => $coordinator?->name ?: 'Korwil belum diatur',
                'korwil_photo' => $coordinator?->photoUrl(),
                'units' => $units,
            ];
        }

        return [
            'leader' => [
                'label' => $leaderLabel,
                'name' => $leader->name,
                'photo' => $leader->photoUrl(),
                'registrasi' => $visibleTotal,
            ],
            'regionals' => $regionalCards,
            'note' => 'Semua regional, unit yang tampil hanya yang memiliki closing',
        ];
    }

    /**
     * The "Closing Personal Per Regional" source appends a "Tim Terpilih"
     * suffix to some staff_name values (a cohort tag from cb.web.id, not
     * part of the person's actual name) — strip it before it reaches
     * display, self-matching, or the rsm_users name lookup.
     */
    private static function cleanStaffName(string $name): string
    {
        $name = trim($name);
        $stripped = preg_replace('/\s+Tim Terpilih$/i', '', $name);

        return trim($stripped ?? $name);
    }

    /** A campus row's own label, translated through CAMPUS_ALIASES if it's a merge source. */
    private static function canonicalCampusLabel(string $label): string
    {
        return self::CAMPUS_ALIASES[mb_strtolower(trim($label))] ?? trim($label);
    }

    /**
     * Campus rows grouped by (regional, canonical label) so aliased rows —
     * e.g. "IKIP Widya Darma" and "STIE Widya Darma", administratively one
     * unit — sum into a single entry instead of two. `breakdown` keeps each
     * original source row's own label/total so a merged card can still show
     * "IKIP Widya Darma: 4 · STIE Widya Darma: 5", not just the sum.
     *
     * @return list<array{regional: string, label: string, total: float, breakdown: list<array{label: string, total: float}>}>
     */
    private static function indexCampusRows(array $campusTotals): array
    {
        $rows = [];
        foreach ($campusTotals['rows'] as $row) {
            $label = trim((string) $row['unit']);
            $value = (float) $row['registrasi'];
            if ($label === '' || $value <= 0) {
                continue;
            }
            $regional = (string) ($row['regional'] ?: 'Tanpa Regional');
            $canonical = self::canonicalCampusLabel($label);
            $key = mb_strtolower($regional).'|'.mb_strtolower($canonical);
            $rows[$key] ??= ['regional' => $regional, 'label' => $canonical, 'total' => 0.0, 'breakdown' => []];
            $rows[$key]['total'] += $value;
            $rows[$key]['breakdown'][] = ['label' => $label, 'total' => $value];
        }

        return array_values($rows);
    }

    /** @param list<array{regional: string, label: string, total: float, breakdown: list<array{label: string, total: float}>}> $campusRows */
    private static function matchCampusRow(array $campusRows, string $regional, string $unitLabel): ?array
    {
        $candidates = self::unitMatchKeys($unitLabel);
        if ($candidates === []) {
            return null;
        }

        foreach ($campusRows as $index => $row) {
            if (mb_strtolower($row['regional']) !== mb_strtolower($regional)) {
                continue;
            }
            $rowKeys = self::unitMatchKeys($row['label']);
            if (array_intersect($candidates, $rowKeys) !== []) {
                return ['index' => $index, 'label' => $row['label'], 'total' => $row['total'], 'breakdown' => $row['breakdown']];
            }
        }

        return null;
    }

    /**
     * Candidate match keys for a campus/unit label: the label itself, its
     * "plain" form with parenthetical content stripped, any parenthetical
     * content alone — covers "Full Name (ABBR)" vs "ABBR"-only naming
     * across rsm_users.campus_name and the collab "Closing Kampus Regional"
     * source — and, for a combined "A / B" display label (two real
     * campuses merged into one card via CAMPUS_ALIASES), each side alone,
     * so a raw unit label matching just "A" still finds the merged row.
     *
     * @return list<string>
     */
    private static function unitMatchKeys(string $label): array
    {
        $label = trim($label);
        if ($label === '') {
            return [];
        }

        $keys = [mb_strtolower($label)];

        $plain = trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/\s*[\(\[].*?[\)\]]\s*/', ' ', $label)));
        if ($plain !== '' && ! in_array(mb_strtolower($plain), $keys, true)) {
            $keys[] = mb_strtolower($plain);
        }

        if (preg_match_all('/[\(\[]([^\)\]]+)[\)\]]/', $label, $matches)) {
            foreach ($matches[1] as $paren) {
                $parenKey = mb_strtolower(trim($paren));
                if ($parenKey !== '' && ! in_array($parenKey, $keys, true)) {
                    $keys[] = $parenKey;
                }
            }
        }

        if (str_contains($label, '/')) {
            foreach (explode('/', $label) as $segment) {
                $segmentKey = mb_strtolower(trim($segment));
                if ($segmentKey !== '' && ! in_array($segmentKey, $keys, true)) {
                    $keys[] = $segmentKey;
                }
            }
        }

        if (isset(self::CAMPUS_ALIASES[$keys[0]])) {
            $aliasKey = mb_strtolower(self::CAMPUS_ALIASES[$keys[0]]);
            if (! in_array($aliasKey, $keys, true)) {
                $keys[] = $aliasKey;
            }
        }

        return $keys;
    }
}
