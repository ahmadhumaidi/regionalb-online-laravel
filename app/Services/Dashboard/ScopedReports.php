<?php

namespace App\Services\Dashboard;

use App\Models\RsmReport;
use App\Models\RsmUser;
use Illuminate\Database\Eloquent\Builder;

/** Shared base query (filters + role scope) reused by overview and gamification aggregation. */
class ScopedReports
{
    public static function query(string $area, array $filters, RsmUser $user): Builder
    {
        $query = RsmReport::query()
            ->where('area', $area)
            ->whereBetween('report_date', [$filters['date_from'], $filters['date_to']]);

        if ($filters['wilayah'] !== '') {
            $query->where('wilayah', $filters['wilayah']);
        }
        if ($filters['unit_name'] !== '') {
            $query->where('unit_name', $filters['unit_name']);
        }
        if ($filters['staff_name'] !== '') {
            $query->where('staff_name', $filters['staff_name']);
        }

        return ReportScope::apply($query, $user);
    }
}
