<?php

namespace App\Services\Dashboard;

use App\Models\RsmUser;
use App\Support\AreaRegionals;
use App\Support\CampusMatcher;

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
            $name = (string) $row['name'];
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
        ];
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
            $canonical = CampusMatcher::canonicalLabel($label);
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
        foreach ($campusRows as $index => $row) {
            if (mb_strtolower($row['regional']) !== mb_strtolower($regional)) {
                continue;
            }
            if (CampusMatcher::matches($unitLabel, $row['label'])) {
                return ['index' => $index, 'label' => $row['label'], 'total' => $row['total'], 'breakdown' => $row['breakdown']];
            }
        }

        return null;
    }
}
