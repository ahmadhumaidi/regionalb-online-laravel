<?php

namespace App\Services\AdBudget;

use App\Models\RsmAdBudgetLimit;
use App\Models\RsmUser;
use App\Support\AreaRegionals;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ports rsm_ad_budget_summaries() (rsm_db.php:2084-2136) for the "Plafon
 * Anggaran Regional" panel: one card per wilayah showing the monthly cap
 * and how much of it is used. Rejected requests don't count against the
 * cap, matching rsm_ad_budget_used()'s `status <> 'Ditolak'` filter.
 */
class AdBudgetLimitService
{
    /** @return list<array{wilayah: string, unit_name: string, budget_limit: float, requested: float, approved: float, realization: float, remaining: float, count: int}> */
    public static function build(string $area, string $period, RsmUser $user): array
    {
        $regionals = AreaRegionals::forArea($area);
        $hasUnitName = Schema::hasColumn('rsm_ad_budget_limits', 'unit_name');

        if (in_array($user->role, ['koordinator', 'staff'], true) && trim((string) $user->regional) !== '') {
            $regionals = [$user->regional];
        }

        $limitRows = RsmAdBudgetLimit::query()
            ->where('area', $area)
            ->where('ad_period', $period)
            ->whereIn('wilayah', $regionals)
            ->orderBy('wilayah')
            ->when($hasUnitName, fn ($query) => $query->orderBy('unit_name'))
            ->get();

        $usage = DB::table('rsm_reports')
            ->select('wilayah')
            ->selectRaw('unit_name')
            ->selectRaw('SUM(budget_requested) as requested, SUM(budget_approved) as approved, SUM(realization_amount) as realization, COUNT(*) as report_count')
            ->where('area', $area)
            ->where('report_type', 'ads')
            ->where('ad_period', $period)
            ->whereIn('wilayah', $regionals)
            ->whereRaw('LOWER(status) <> ?', ['ditolak'])
            ->groupBy('wilayah', 'unit_name')
            ->get()
            ->keyBy(fn ($row) => self::scopeKey((string) $row->wilayah, (string) $row->unit_name));

        $rows = $limitRows->map(function (RsmAdBudgetLimit $limit) use ($usage, $hasUnitName) {
            $unitName = $hasUnitName ? (string) $limit->unit_name : '';
            $use = $usage->get(self::scopeKey((string) $limit->wilayah, $unitName));

            $budgetLimit = (float) ($limit->budget_limit ?? 0);
            $requested = (float) ($use->requested ?? 0);

            return [
                'wilayah' => (string) $limit->wilayah,
                'unit_name' => $unitName,
                'budget_limit' => $budgetLimit,
                'requested' => $requested,
                'approved' => (float) ($use->approved ?? 0),
                'realization' => (float) ($use->realization ?? 0),
                'remaining' => $budgetLimit - $requested,
                'count' => (int) ($use->report_count ?? 0),
            ];
        });

        return $rows->values()->all();
    }

    /** Port of rsm_save_ad_budget_limit() (rsm_db.php:2000-2032). */
    public static function save(string $area, string $period, string $wilayah, string $unitName, float $budgetLimit, ?string $notes, RsmUser $actor): void
    {
        if ($period === '' || $wilayah === '' || $unitName === '') {
            throw new \InvalidArgumentException('Periode, regional, dan kampus wajib diisi.');
        }
        if ($budgetLimit <= 0) {
            throw new \InvalidArgumentException('Besaran anggaran kampus harus lebih dari 0.');
        }

        $hasUnitName = Schema::hasColumn('rsm_ad_budget_limits', 'unit_name');
        $attributes = ['area' => $area, 'ad_period' => $period, 'wilayah' => $wilayah];
        if ($hasUnitName) {
            $attributes['unit_name'] = $unitName;
        }
        $values = [
            'budget_limit' => $budgetLimit,
            'notes' => $notes,
            'created_by_user_id' => $actor->id,
            'created_by_name' => $actor->name,
        ];
        if ($hasUnitName) {
            $values['unit_name'] = $unitName;
        }

        RsmAdBudgetLimit::updateOrCreate(
            $attributes,
            $values
        );
    }

    private static function scopeKey(string $wilayah, string $unitName): string
    {
        return mb_strtolower(trim($wilayah)).'|'.mb_strtolower(trim($unitName));
    }
}
