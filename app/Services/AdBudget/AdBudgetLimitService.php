<?php

namespace App\Services\AdBudget;

use App\Models\RsmAdBudgetLimit;
use App\Models\RsmUser;
use App\Support\AreaRegionals;
use Illuminate\Support\Facades\DB;

/**
 * Ports rsm_ad_budget_summaries() (rsm_db.php:2084-2136) for the "Plafon
 * Anggaran Regional" panel: one card per wilayah showing the monthly cap
 * and how much of it is used. Rejected requests don't count against the
 * cap, matching rsm_ad_budget_used()'s `status <> 'Ditolak'` filter.
 */
class AdBudgetLimitService
{
    /** @return list<array{wilayah: string, budget_limit: float, requested: float, approved: float, realization: float, remaining: float, count: int}> */
    public static function build(string $area, string $period, RsmUser $user): array
    {
        $regionals = AreaRegionals::forArea($area);

        if (in_array($user->role, ['koordinator', 'staff'], true) && trim((string) $user->regional) !== '') {
            $regionals = [$user->regional];
        }

        $limits = RsmAdBudgetLimit::query()
            ->where('area', $area)
            ->where('ad_period', $period)
            ->whereIn('wilayah', $regionals)
            ->get()
            ->keyBy('wilayah');

        $usage = DB::table('rsm_reports')
            ->select('wilayah')
            ->selectRaw('SUM(budget_requested) as requested, SUM(budget_approved) as approved, SUM(realization_amount) as realization, COUNT(*) as report_count')
            ->where('area', $area)
            ->where('report_type', 'ads')
            ->where('ad_period', $period)
            ->whereIn('wilayah', $regionals)
            ->whereRaw('LOWER(status) <> ?', ['ditolak'])
            ->groupBy('wilayah')
            ->get()
            ->keyBy('wilayah');

        return collect($regionals)->map(function (string $wilayah) use ($limits, $usage) {
            $limit = $limits->get($wilayah);
            $use = $usage->get($wilayah);

            $budgetLimit = (float) ($limit->budget_limit ?? 0);
            $requested = (float) ($use->requested ?? 0);

            return [
                'wilayah' => $wilayah,
                'budget_limit' => $budgetLimit,
                'requested' => $requested,
                'approved' => (float) ($use->approved ?? 0),
                'realization' => (float) ($use->realization ?? 0),
                'remaining' => $budgetLimit - $requested,
                'count' => (int) ($use->report_count ?? 0),
            ];
        })->values()->all();
    }

    /** Port of rsm_save_ad_budget_limit() (rsm_db.php:2000-2032). */
    public static function save(string $area, string $period, string $wilayah, float $budgetLimit, ?string $notes, RsmUser $actor): void
    {
        if ($period === '' || $wilayah === '') {
            throw new \InvalidArgumentException('Periode dan regional wajib diisi.');
        }
        if ($budgetLimit <= 0) {
            throw new \InvalidArgumentException('Besaran anggaran regional harus lebih dari 0.');
        }

        RsmAdBudgetLimit::updateOrCreate(
            ['area' => $area, 'ad_period' => $period, 'wilayah' => $wilayah],
            [
                'budget_limit' => $budgetLimit,
                'notes' => $notes,
                'created_by_user_id' => $actor->id,
                'created_by_name' => $actor->name,
            ]
        );
    }
}
