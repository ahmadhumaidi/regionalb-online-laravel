<?php

namespace App\Services\Dashboard;

use App\Models\RsmCollabDailyMetric;
use App\Models\RsmUser;
use App\Support\AreaRegionals;
use App\Support\CampusMatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads rsm_collab_daily_metrics, a synced cache of the external
 * /sumber-collab reports — the source of truth for displayed
 * Registrasi/Herregistrasi, not rsm_reports. "Closing Collab" and "Herreg
 * Collab" (the original per-staff pair this ported from rsm_collab_staff_
 * performance(), rsm_db.php:6023) are no longer considered reliable;
 * per-staff figures now come from "Closing Personal Per Regional" alone
 * (personalPerformance()) — Herreg Personal has no source yet.
 */
class CollabMetricsService
{
    /** @return array{rows: array, max_value: float, synced_at: ?string} */
    public static function campusTotals(array $filters, string $area, RsmUser $user, string $reportName = 'Closing Kampus Regional'): array
    {
        $regionals = self::allowedRegionals($area, $filters, $user);

        $query = RsmCollabDailyMetric::query()
            ->where('report_name', $reportName)
            ->whereBetween('metric_date', [$filters['date_from'], $filters['date_to']])
            ->whereIn('regional', $regionals);

        if ($filters['unit_name'] !== '') {
            $query->where('campus_name', $filters['unit_name']);
        }

        $rows = $query
            ->select('entity_key')
            ->selectRaw('MAX(regional) as regional, MAX(campus_name) as campus_name, SUM(value) as total_value')
            ->groupBy('entity_key')
            ->get()
            ->map(fn ($row) => [
                'regional' => $row->regional,
                'unit' => $row->campus_name,
                'registrasi' => (float) $row->total_value,
            ]);

        // No explicit unit_name filter (the staff filter-bar box is
        // read-only display only, never actually submitted) — auto-scope to
        // the staff member's own campus, fuzzy-matched via CampusMatcher
        // since rsm_users.campus_name and this Collab source don't always
        // spell the same campus identically.
        $ownCampus = trim((string) $user->campus_name);
        if ($user->role === 'staff' && $filters['unit_name'] === '' && $ownCampus !== '') {
            $rows = $rows->filter(fn (array $row) => CampusMatcher::matches((string) $row['unit'], $ownCampus))->values();
        }

        $rows = $rows->sortByDesc('registrasi')->values();

        return [
            'rows' => $rows->all(),
            'max_value' => (float) ($rows->max('registrasi') ?? 0),
            'synced_at' => self::syncedAt(),
        ];
    }

    private static function staffTotals(string $reportName, array $filters, string $area, RsmUser $user): Collection
    {
        $regionals = self::allowedRegionals($area, $filters, $user);
        $staffFilter = $filters['staff_name'];
        if ($user->role === 'staff' && $staffFilter === '') {
            $staffFilter = (string) $user->name;
        }

        $query = RsmCollabDailyMetric::query()
            ->where('report_name', $reportName)
            ->whereBetween('metric_date', [$filters['date_from'], $filters['date_to']])
            ->whereIn('regional', $regionals);

        if ($staffFilter !== '') {
            $query->where('staff_name', $staffFilter);
        }

        return $query
            ->select('entity_key')
            ->selectRaw('MAX(staff_nik) as staff_nik, MAX(staff_name) as staff_name, MAX(regional) as regional, SUM(value) as total_value')
            ->groupBy('entity_key')
            ->get()
            ->keyBy(fn ($row) => self::staffKey($row));
    }

    /** Sums a single staff-level report (e.g. "Closing Personal Per Regional") across the allowed regionals/filters. */
    public static function personalTotal(array $filters, string $area, RsmUser $user, string $reportName): float
    {
        return (float) self::staffTotals($reportName, $filters, $area, $user)->sum('total_value');
    }

    /**
     * Per-staff performance sourced from "Closing Personal Per Regional" +
     * "Herreg Personal Per Regional" — the old "Closing Collab"/"Herreg
     * Collab" pairing is no longer considered reliable. Shape:
     * rows/regional_summary/totals/sources — used wherever per-staff
     * ranking is needed.
     */
    public static function personalPerformance(string $area, array $filters, RsmUser $user): array
    {
        $closing = self::staffTotals('Closing Personal Per Regional', $filters, $area, $user);
        $herreg = self::staffTotals('Herreg Personal Per Regional', $filters, $area, $user);

        $rows = $closing->keys()->merge($herreg->keys())->unique()->map(function ($key) use ($closing, $herreg) {
            $source = $closing->get($key) ?? $herreg->get($key);

            return [
                'staff_key' => $key,
                'nik' => $source->staff_nik ?? null,
                'name' => self::cleanStaffName((string) ($source->staff_name ?? '-')),
                'regional' => $source->regional ?? '-',
                'registrasi' => (float) ($closing->get($key)->total_value ?? 0),
                'herregistrasi' => (float) ($herreg->get($key)->total_value ?? 0),
            ];
        })->sortBy([
            ['regional', 'asc'],
            ['registrasi', 'desc'],
            ['herregistrasi', 'desc'],
            ['name', 'asc'],
        ])->values();

        $regionalSummary = $rows->groupBy('regional')->map(fn ($group, $regional) => [
            'regional' => $regional,
            'staff_count' => $group->count(),
            'registrasi' => $group->sum('registrasi'),
            'herregistrasi' => $group->sum('herregistrasi'),
            'total' => $group->sum('registrasi') + $group->sum('herregistrasi'),
        ])->values();

        return [
            'rows' => $rows->all(),
            'regional_summary' => $regionalSummary->all(),
            'totals' => [
                'staff_count' => $rows->count(),
                'registrasi' => $rows->sum('registrasi'),
                'herregistrasi' => $rows->sum('herregistrasi'),
            ],
            'sources' => [
                'registrasi' => ['label' => 'Closing Personal Per Regional'],
                'herregistrasi' => ['label' => 'Herreg Personal Per Regional'],
            ],
        ];
    }

    /** @return list<string> */
    private static function allowedRegionals(string $area, array $filters, RsmUser $user): array
    {
        $regionals = $filters['wilayah'] !== '' ? [$filters['wilayah']] : AreaRegionals::forArea($area);

        if (in_array($user->role, ['koordinator', 'staff'], true) && trim((string) $user->regional) !== '') {
            $regionals = [$user->regional];
        }

        return $regionals;
    }

    private static function staffKey(object $row): string
    {
        $nik = trim((string) ($row->staff_nik ?? ''));

        return $nik !== '' ? 'nik:'.$nik : 'name:'.mb_strtolower(trim((string) $row->staff_name));
    }

    /**
     * The "Closing Personal Per Regional" source appends a "Tim Terpilih"
     * suffix to some staff_name values (a cohort tag from cb.web.id, not
     * part of the person's actual name) - strip it before it reaches
     * display, self-matching, or the rsm_users name lookup.
     */
    private static function cleanStaffName(string $name): string
    {
        $name = trim($name);
        $stripped = preg_replace('/\s+Tim Terpilih$/i', '', $name);

        return trim($stripped ?? $name);
    }

    public static function syncedAt(): ?string
    {
        return DB::table('rsm_collab_daily_metrics')->max('metric_date');
    }
}
