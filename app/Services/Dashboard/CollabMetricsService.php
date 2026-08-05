<?php

namespace App\Services\Dashboard;

use App\Models\RsmCollabDailyMetric;
use App\Models\RsmUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ports rsm_collab_campus_totals()/rsm_collab_staff_totals()/
 * rsm_collab_staff_performance() (rsm_db.php:6100/5946/6023). These read
 * rsm_collab_daily_metrics, a synced cache of the external "Closing Collab"
 * / "Herreg Collab" / "Closing Kampus Regional" reports — the source of
 * truth for displayed Registrasi/Herregistrasi, not rsm_reports.
 */
class CollabMetricsService
{
    /** @return array{rows: array, max_value: float, synced_at: ?string} */
    public static function campusTotals(array $filters, string $area, RsmUser $user): array
    {
        $regionals = self::allowedRegionals($area, $filters, $user);

        $query = RsmCollabDailyMetric::query()
            ->where('report_name', 'Closing Kampus Regional')
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
            ])
            ->sortByDesc('registrasi')
            ->values();

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

    /** Ports rsm_collab_staff_performance() — merges Closing Collab + Herreg Collab by staff. */
    public static function staffPerformance(string $area, array $filters, RsmUser $user): array
    {
        $closing = self::staffTotals('Closing Collab', $filters, $area, $user);
        $herreg = self::staffTotals('Herreg Collab', $filters, $area, $user);

        $rows = $closing->keys()->merge($herreg->keys())->unique()->map(function ($key) use ($closing, $herreg) {
            $source = $closing->get($key) ?? $herreg->get($key);

            return [
                'staff_key' => $key,
                'nik' => $source->staff_nik ?? null,
                'name' => $source->staff_name ?? '-',
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
                'registrasi' => ['label' => 'Closing Collab'],
                'herregistrasi' => ['label' => 'Herreg Collab'],
            ],
        ];
    }

    /** @return list<string> */
    private static function allowedRegionals(string $area, array $filters, RsmUser $user): array
    {
        $regionals = $filters['wilayah'] !== '' ? [$filters['wilayah']] : self::areaRegionals($area);

        if (in_array($user->role, ['koordinator', 'staff'], true) && trim((string) $user->regional) !== '') {
            $regionals = [$user->regional];
        }

        return $regionals;
    }

    /** @return list<string> */
    private static function areaRegionals(string $area): array
    {
        return str_contains($area, 'B')
            ? ['Regional 4', 'Regional 5', 'Regional 6', 'Regional 7']
            : ['Regional 1', 'Regional 2', 'Regional 3'];
    }

    private static function staffKey(object $row): string
    {
        $nik = trim((string) ($row->staff_nik ?? ''));

        return $nik !== '' ? 'nik:'.$nik : 'name:'.mb_strtolower(trim((string) $row->staff_name));
    }

    public static function syncedAt(): ?string
    {
        return DB::table('rsm_collab_daily_metrics')->max('metric_date');
    }
}
