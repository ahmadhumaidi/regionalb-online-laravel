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
 */
class AchievementReportService
{
    private const SENIOR_ROLES = ['super_user', 'executive_director', 'director', 'senior'];

    public static function build(string $area, array $filters, RsmUser $user): array
    {
        $performance = CollabMetricsService::personalPerformance($area, $filters, $user);
        $campusTotals = CollabMetricsService::campusTotals($filters, $area, $user);
        $campusByRegional = [];
        foreach ($campusTotals['rows'] as $row) {
            $regional = (string) ($row['regional'] ?: 'Tanpa Regional');
            $campusByRegional[$regional] = ($campusByRegional[$regional] ?? 0.0) + (float) $row['registrasi'];
        }

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
        foreach ($performance['rows'] as $row) {
            $registrasi = (float) $row['registrasi'];
            if ($registrasi <= 0) {
                continue;
            }
            $name = trim((string) $row['name']);
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
            $unit = trim((string) ($matchedUser->campus_name ?? '')) ?: 'Unit belum diatur';

            $regionalUnits[$regional][$unit]['unit'] ??= $unit;
            $regionalUnits[$regional][$unit]['registrasi'] = ($regionalUnits[$regional][$unit]['registrasi'] ?? 0) + $registrasi;
            $regionalUnits[$regional][$unit]['herregistrasi'] = ($regionalUnits[$regional][$unit]['herregistrasi'] ?? 0) + (float) $row['herregistrasi'];
            $regionalUnits[$regional][$unit]['staff'][] = [
                'name' => $name,
                'photo' => $matchedUser?->photoUrl(),
                'registrasi' => $registrasi,
                'herregistrasi' => (float) $row['herregistrasi'],
            ];
        }

        $regionals = $scopedRegionals !== [] ? $scopedRegionals : array_keys($regionalUnits);
        $visibleTotal = 0.0;
        foreach ($regionalUnits as $units) {
            foreach ($units as $unit) {
                $visibleTotal += (float) $unit['registrasi'];
            }
        }

        [$leader, $leaderLabel] = match ($user->role) {
            'koordinator' => [$user, 'Korwil'],
            'staff' => [$user, 'Staff'],
            default => [$senior ?: $user, 'Senior Manager'],
        };

        $regionalCards = [];
        foreach ($regionals as $regional) {
            $units = collect($regionalUnits[$regional] ?? [])
                ->map(function (array $unit) {
                    usort($unit['staff'], fn (array $a, array $b) => $b['registrasi'] <=> $a['registrasi']);

                    return $unit;
                })
                ->sortByDesc('registrasi')
                ->values()
                ->all();
            $regionalTotal = array_sum(array_column($units, 'registrasi'));
            $coordinator = $coordinatorsByRegional[$regional] ?? null;

            // Only meaningful when $regionalTotal already reflects every staff in the
            // regional (koordinator/senior view) — for a 'staff' viewer $regionalTotal
            // is filtered down to just themselves, so the diff would wrongly count
            // other staff's closing as "non-staff".
            $nonStaff = $user->role !== 'staff'
                ? max(0.0, ($campusByRegional[$regional] ?? 0.0) - $regionalTotal)
                : 0.0;

            $regionalCards[] = [
                'regional' => $regional,
                'registrasi' => $regionalTotal,
                'non_staff_registrasi' => $nonStaff,
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
}
