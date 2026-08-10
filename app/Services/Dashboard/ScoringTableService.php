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
            ->map(function (RsmUser $staff) use ($indicatorByName, $personalByName, $campusRegistrasi, $campusHerreg, $shareFbByName, $liveStreamingByName, $affMhsByName, $affNonMhsByName) {
                $nameKey = mb_strtolower(trim((string) $staff->name));
                $indicator = $indicatorByName->get($nameKey);
                $personal = $personalByName->get($nameKey);
                $unitName = (string) ($indicator['unit_name'] ?? $staff->campus_name ?? '');
                $wilayah = (string) ($indicator['wilayah'] ?? $staff->regional ?? '');

                return [
                    'name' => $staff->name,
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
            })
            ->sortBy([['wilayah', 'asc'], ['name', 'asc']])
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

        return $query->orderBy('regional')->orderBy('name')->get();
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
