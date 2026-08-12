<?php

namespace App\Services\Dashboard;

use App\Models\RsmMonthlyTarget;
use App\Models\RsmUser;
use App\Support\CampusMatcher;
use Illuminate\Support\Collection;

/**
 * "Scoring" menu: one wide table collecting every assessment indicator
 * already tracked elsewhere in the system (personal registrasi/herreg,
 * campus registrasi/herreg, ad reports, follow ups, leads, report volume)
 * into a single row per staff member, then scoring it against the configured
 * monthly target and weight per indicator.
 *
 * The row set is the full staff roster (RsmUser), not just staff who
 * happen to have report data for the current filter - a staff member with
 * zero activity this period still shows up, with every indicator at 0,
 * instead of silently disappearing from the table.
 */
class ScoringTableService
{
    /** @return array{rows: list<array>, synced_at: ?string} */
    public static function build(string $area, array $filters, RsmUser $user): array
    {
        $roster = self::staffRoster($area, $user);
        $indicators = (array) config('scoring_indicators.indicators', []);
        $targetsByName = self::monthlyTargetsByName($area, $filters, $user, $roster);

        $indicatorByName = GamificationService::indicatorRows($area, $filters, $user)
            ->keyBy(fn (array $row) => mb_strtolower(trim((string) $row['name'])));

        // Registrasi/Herreg Personal & Kampus all come straight from the
        // /sumber-collab sync (rsm_collab_daily_metrics), not the ad_leads-
        // derived fallback GamificationService blends in for its points
        // formula - keeps every "registrasi"/"herreg" number on this table
        // sourced consistently, personal and kampus alike.
        $personalPerformance = CollabMetricsService::personalPerformance($area, $filters, $user);
        $personalByName = collect($personalPerformance['rows'])->keyBy(fn (array $row) => mb_strtolower(trim((string) $row['name'])));
        $campusRegistrasi = self::campusIndex(CollabMetricsService::campusTotals($filters, $area, $user, 'Closing Kampus Regional'));
        $campusHerreg = self::campusIndex(CollabMetricsService::campusTotals($filters, $area, $user, 'Herreg Kampus Regional'));
        $shareFbByName = CollabMetricsService::personalTotalsByName($filters, $area, $user, 'Share FB Group');
        $liveStreamingByName = CollabMetricsService::personalTotalsByName($filters, $area, $user, 'Live Streaming');
        $affMhsByName = CollabMetricsService::personalTotalsByName($filters, $area, $user, 'Affiliator Mahasiswa');
        $affNonMhsByName = CollabMetricsService::personalTotalsByName($filters, $area, $user, 'Affiliator Non Mahasiswa');

        $rows = $roster
            ->map(function (RsmUser $staff) use ($indicatorByName, $personalByName, $campusRegistrasi, $campusHerreg, $shareFbByName, $liveStreamingByName, $affMhsByName, $affNonMhsByName, $indicators, $targetsByName) {
                $nameKey = mb_strtolower(trim((string) $staff->name));
                $indicator = $indicatorByName->get($nameKey);
                $personal = $personalByName->get($nameKey);
                $unitName = (string) ($indicator['unit_name'] ?? $staff->campus_name ?? '');
                $wilayah = (string) ($indicator['wilayah'] ?? $staff->regional ?? '');

                $row = [
                    'user_id' => $staff->id,
                    'name' => $staff->name,
                    'photo_path' => $staff->photoUrl(),
                    'wilayah' => $wilayah !== '' ? $wilayah : '-',
                    'unit_name' => $unitName !== '' ? $unitName : '-',
                    'registrasi_personal' => (float) ($personal['registrasi'] ?? 0),
                    'herregistrasi_personal' => (float) ($personal['herregistrasi'] ?? 0),
                    'registrasi_kampus' => self::lookupCampus($unitName, $campusRegistrasi),
                    'herregistrasi_kampus' => self::lookupCampus($unitName, $campusHerreg),
                    'laporan_iklan' => (int) ($indicator['uploaded_ad_reports'] ?? 0),
                    'realisasi_iklan' => (float) ($indicator['spend_total'] ?? 0),
                    'follow_up_total' => (int) ($indicator['follow_up_total'] ?? 0),
                    'leads_total' => (int) ($indicator['leads_total'] ?? 0),
                    'laporan_total' => (int) ($indicator['report_total'] ?? 0),
                    'hari_aktif' => (int) ($indicator['report_days'] ?? 0),
                    'share_fb_group' => (float) ($shareFbByName->get($nameKey) ?? 0),
                    'live_streaming' => (float) ($liveStreamingByName->get($nameKey) ?? 0),
                    'affiliator_mahasiswa' => (float) ($affMhsByName->get($nameKey) ?? 0),
                    'affiliator_non_mahasiswa' => (float) ($affNonMhsByName->get($nameKey) ?? 0),
                ];

                return self::withScore($row, $indicators, $targetsByName->get($nameKey));
            })
            ->sortBy([['total_score', 'desc'], ['wilayah', 'asc'], ['name', 'asc']])
            ->values();

        return [
            'rows' => $rows->all(),
            'synced_at' => CollabMetricsService::syncedAt(),
        ];
    }

    /**
     * Every active staff account in scope: koordinator sees their own
     * wilayah, everyone else who can reach this page (senior tier, mentor -
     * canViewScoringTable() already blocks plain staff) sees the whole
     * area, mirroring ReportScope::apply()'s per-role widening rules.
     *
     * @return Collection<int, RsmUser>
     */
    private static function staffRoster(string $area, RsmUser $user): Collection
    {
        $query = RsmUser::query()
            ->where('role', RsmUser::ROLE_STAFF)
            ->where('area', $area)
            ->where('is_active', true);

        if ($user->role === RsmUser::ROLE_KOORDINATOR && trim((string) $user->regional) !== '') {
            $query->where('regional', $user->regional);
        }
        if ($user->role === RsmUser::ROLE_STAFF) {
            $query->where('id', $user->id);
        }

        return $query->orderBy('regional')->orderBy('name')->get();
    }

    /** @param Collection<int, RsmUser> $roster */
    private static function monthlyTargetsByName(string $area, array $filters, RsmUser $user, Collection $roster): Collection
    {
        $targetMonth = substr($filters['date_from'] ?: now()->toDateString(), 0, 7);
        $staffNames = $roster->pluck('name')->filter()->values()->all();

        $query = RsmMonthlyTarget::query()
            ->where('area', $area)
            ->where('target_month', $targetMonth)
            ->where('scope_type', 'staff')
            ->whereIn('staff_name', $staffNames);

        if ($user->role === RsmUser::ROLE_KOORDINATOR && trim((string) $user->regional) !== '') {
            $query->where('wilayah', $user->regional);
        }
        if ($filters['wilayah'] !== '') {
            $query->where('wilayah', $filters['wilayah']);
        }
        if ($filters['unit_name'] !== '') {
            $query->where('unit_name', $filters['unit_name']);
        }
        if ($filters['staff_name'] !== '') {
            $query->where('staff_name', $filters['staff_name']);
        }

        return $query->get()->keyBy(fn (RsmMonthlyTarget $target) => mb_strtolower(trim((string) $target->staff_name)));
    }

    private static function withScore(array $row, array $indicators, ?RsmMonthlyTarget $target): array
    {
        $targetRows = self::targetRows($target);
        $scoreDetails = [];
        $totalScore = 0.0;
        $totalWeight = 0.0;

        foreach ($indicators as $key => $meta) {
            $metricKey = (string) ($meta['metric_key'] ?? '');
            $actual = (float) ($row[$metricKey] ?? 0);
            $hasTargetRow = array_key_exists($key, $targetRows);
            $targetValue = $hasTargetRow ? (float) ($targetRows[$key]['target'] ?? 0) : 0.0;
            $weight = $hasTargetRow ? (float) ($targetRows[$key]['weight'] ?? $meta['default_weight'] ?? 0) : 0.0;
            $score = match (true) {
                in_array($key, ['lap_iklan', 'realisasi_iklan', 'leads'], true) && $hasTargetRow && $targetValue <= 0 && $weight > 0 => $weight,
                $targetValue > 0 && $weight > 0 => min($actual / $targetValue, 1.0) * $weight,
                default => 0.0,
            };

            $scoreDetails[$key] = [
                'actual' => $actual,
                'target' => $targetValue,
                'weight' => $weight,
                'score' => $score,
            ];
            $totalScore += $score;
            $totalWeight += $weight;
        }

        $row['score_details'] = $scoreDetails;
        $row['total_score'] = round($totalScore, 2);
        $row['total_weight'] = round($totalWeight, 2);
        $row['target_month'] = $target?->target_month;

        return $row;
    }

    private static function targetRows(?RsmMonthlyTarget $target): array
    {
        if (! $target) {
            return [];
        }
        if (is_array($target->indicator_targets) && $target->indicator_targets !== []) {
            return $target->indicator_targets;
        }

        return [
            'reg' => ['target' => (float) $target->target_registrasi],
            'herreg' => ['target' => (float) $target->target_herregistrasi],
            'fu' => ['target' => (float) $target->target_follow_up],
            'leads' => ['target' => (float) $target->target_leads],
            'realisasi_iklan' => ['target' => (float) $target->target_anggaran],
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
