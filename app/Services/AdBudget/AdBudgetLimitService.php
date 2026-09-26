<?php

namespace App\Services\AdBudget;

use App\Models\RsmAdBudgetLimit;
use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Reports\ReportFormService;
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
    /** @return list<array{wilayah: string, unit_name: string, owner_name: string, budget_limit: float, requested: float, approved: float, realization: float, remaining: float, count: int}> */
    public static function build(string $area, string $period, RsmUser $user): array
    {
        $regionals = AreaRegionals::forArea($area);
        $hasUnitName = Schema::hasColumn('rsm_ad_budget_limits', 'unit_name');

        if ($user->role === RsmUser::ROLE_SUPER_USER) {
            array_unshift($regionals, $area);
        }

        if (in_array($user->role, ['koordinator', 'staff'], true) && trim((string) $user->regional) !== '') {
            $regionals = [$user->regional];
        }

        $limitRows = RsmAdBudgetLimit::query()
            ->where('area', $area)
            ->where('ad_period', $period)
            ->whereIn('wilayah', $regionals)
            ->when(
                $user->role === RsmUser::ROLE_STAFF,
                function ($query) use ($user, $hasUnitName): void {
                    if (! $hasUnitName || blank($user->campus_name)) {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $campus = DB::table('partner_campuses')
                        ->where('display_name', $user->campus_name)
                        ->orWhere('name', $user->campus_name)
                        ->first();
                    $unitNames = collect([
                        $user->campus_name,
                        $campus?->display_name,
                        $campus?->name,
                    ])->filter()->unique()->values()->all();

                    $query->whereIn('unit_name', $unitNames);
                }
            )
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

        $regionalUsage = DB::table('rsm_reports')
            ->select('wilayah')
            ->selectRaw('SUM(budget_requested) as requested, SUM(budget_approved) as approved, SUM(realization_amount) as realization, COUNT(*) as report_count')
            ->where('area', $area)
            ->where('report_type', 'ads')
            ->where('ad_period', $period)
            ->whereIn('wilayah', $regionals)
            ->whereRaw('LOWER(status) <> ?', ['ditolak'])
            ->groupBy('wilayah')
            ->get()
            ->keyBy(fn ($row) => mb_strtolower(trim((string) $row->wilayah)));

        $rows = $limitRows->map(function (RsmAdBudgetLimit $limit) use ($usage, $regionalUsage, $hasUnitName) {
            $unitName = $hasUnitName ? (string) $limit->unit_name : '';
            $use = $unitName === ''
                ? $regionalUsage->get(mb_strtolower(trim((string) $limit->wilayah)))
                : $usage->get(self::scopeKey((string) $limit->wilayah, $unitName));

            $budgetLimit = (float) ($limit->budget_limit ?? 0);
            $requested = (float) ($use->requested ?? 0);

            return [
                'wilayah' => (string) $limit->wilayah,
                'unit_name' => $unitName,
                'owner_name' => (string) ($limit->created_by_name ?? ''),
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
        if ($period === '' || $wilayah === '') {
            throw new \InvalidArgumentException('Periode dan regional wajib diisi.');
        }
        if ($budgetLimit <= 0) {
            throw new \InvalidArgumentException('Besaran anggaran harus lebih dari 0.');
        }

        $hasUnitName = Schema::hasColumn('rsm_ad_budget_limits', 'unit_name');
        $unitName = $hasUnitName ? trim($unitName) : '';

        if ($actor->role === RsmUser::ROLE_KOORDINATOR) {
            if ($unitName === '') {
                throw new \InvalidArgumentException('Kampus/unit wajib dipilih.');
            }

            $regionalLimit = RsmAdBudgetLimit::query()
                ->where(['area' => $area, 'ad_period' => $period, 'wilayah' => $wilayah])
                ->where('unit_name', '')
                ->value('budget_limit');
            if ($regionalLimit === null) {
                throw new \InvalidArgumentException('Plafon regional belum ditetapkan oleh Super User.');
            }

            $allocated = (float) RsmAdBudgetLimit::query()
                ->where(['area' => $area, 'ad_period' => $period, 'wilayah' => $wilayah])
                ->where('unit_name', '<>', '')
                ->sum('budget_limit');
            if ($allocated + $budgetLimit > (float) $regionalLimit) {
                throw new \InvalidArgumentException('Total plafon kampus melebihi plafon regional.');
            }
        } else {
            // Super User/senior menetapkan pool regional; kampus dibagi oleh Korwil.
            $unitName = '';
            $allocated = (float) RsmAdBudgetLimit::query()
                ->where(['area' => $area, 'ad_period' => $period, 'wilayah' => $wilayah])
                ->where('unit_name', '<>', '')
                ->sum('budget_limit');
            if ($budgetLimit < $allocated) {
                throw new \InvalidArgumentException('Plafon regional tidak boleh lebih kecil dari total alokasi kampus yang sudah ditetapkan.');
            }
        }
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

        DB::transaction(function () use ($attributes, $values, $area, $period, $wilayah, $unitName, $budgetLimit, $actor): void {
            if ($actor->role === RsmUser::ROLE_KOORDINATOR) {
                $currentLimit = (float) RsmAdBudgetLimit::query()
                    ->where($attributes)
                    ->lockForUpdate()
                    ->value('budget_limit');
                $values['budget_limit'] = $currentLimit + $budgetLimit;
            }

            RsmAdBudgetLimit::updateOrCreate($attributes, $values);

            // Every coordinator submission is a new approved/disbursed
            // allocation. The aggregate campus limit grows, while each
            // allocation gets its own reporting row for separate evidence.
            if ($actor->role === RsmUser::ROLE_KOORDINATOR) {
                self::syncApprovedCampusReport($area, $period, $wilayah, $unitName, $budgetLimit, $actor);
            }
        });
    }

    /** Remove one campus/unit allocation while retaining its reports as history. */
    public static function deleteUnit(string $area, string $period, string $wilayah, string $unitName): int
    {
        $unitName = trim($unitName);
        if ($unitName === '') {
            throw new \InvalidArgumentException('Kampus/unit wajib dipilih.');
        }

        return RsmAdBudgetLimit::query()
            ->where('area', $area)
            ->where('ad_period', $period)
            ->where('wilayah', $wilayah)
            ->where('unit_name', $unitName)
            ->delete();
    }

    private static function syncApprovedCampusReport(
        string $area,
        string $period,
        string $wilayah,
        string $unitName,
        float $budgetLimit,
        RsmUser $actor
    ): void {
        $isRegionalAdUnit = ReportFormService::isRegionalAdUnit($unitName, $wilayah);
        $campus = $isRegionalAdUnit
            ? null
            : DB::table('partner_campuses')
                ->where('display_name', $unitName)
                ->orWhere('name', $unitName)
                ->first();

        $canonicalUnit = trim((string) ($campus->display_name ?? $unitName));
        $staff = $isRegionalAdUnit
            ? null
            : RsmUser::query()
                ->where('role', RsmUser::ROLE_STAFF)
                ->where('is_active', true)
                ->where('regional', $wilayah)
                ->where(function ($query) use ($canonicalUnit, $campus): void {
                    $query->where('campus_name', $canonicalUnit);
                    if (filled($campus?->name)) {
                        $query->orWhere('campus_name', $campus->name);
                    }
                })
                ->orderBy('id')
                ->first();

        $campaignName = $isRegionalAdUnit
            ? "Anggaran {$canonicalUnit} - {$period}"
            : "Anggaran Iklan {$canonicalUnit} - {$period}";
        RsmReport::create([
            'area' => $area,
            'report_type' => RsmReport::TYPE_ADS,
            'report_date' => now()->toDateString(),
            'user_id' => $staff?->id,
            'partner_campus_id' => $campus?->id,
            'wilayah' => $wilayah,
            'unit_name' => $canonicalUnit,
            'staff_name' => $isRegionalAdUnit ? $actor->name : ($staff?->name ?? ''),
            'created_by_name' => $actor->name,
            'created_by_role' => $actor->role,
            'status' => 'Disetujui',
            'title' => $campaignName,
            'platform' => 'Belum ditentukan',
            'ad_period' => $period,
            'campaign_name' => $campaignName,
            'ad_goal' => 'Leads',
            'budget_requested' => $budgetLimit,
            'budget_approved' => $budgetLimit,
            'realization_amount' => 0,
        ]);
    }

    private static function scopeKey(string $wilayah, string $unitName): string
    {
        return mb_strtolower(trim($wilayah)).'|'.mb_strtolower(trim($unitName));
    }
}
