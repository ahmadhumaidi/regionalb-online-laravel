<?php

namespace App\Services\Dashboard;

use App\Models\RsmUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ports rsm_report_scope_sql() (rsm_db.php:3782) as an Eloquent query
 * constraint applied to RsmReport (or a query joined to it). $column lets
 * callers scope a joined table (e.g. 'rsm_reports.wilayah') instead of the
 * bare column name.
 */
class ReportScope
{
    public static function apply(Builder $query, RsmUser $user, string $prefix = ''): Builder
    {
        $col = fn (string $name) => $prefix !== '' ? "{$prefix}.{$name}" : $name;

        return match ($user->role) {
            'super_user', 'executive_director', 'director', 'senior', 'mentor' => $query,
            'koordinator' => trim((string) $user->regional) !== ''
                ? $query->where($col('wilayah'), $user->regional)
                : $query->whereRaw('1 = 0'),
            'staff' => self::applyStaffScope($query, $user, $col),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private static function applyStaffScope(Builder $query, RsmUser $user, \Closure $col): Builder
    {
        $name = trim((string) $user->name);
        $regional = trim((string) $user->regional);
        $campus = trim((string) $user->campus_name);

        $query->where(function (Builder $q) use ($col, $name) {
            $q->where($col('staff_name'), $name)
                ->orWhere($col('created_by_name'), $name)
                ->orWhere($col('report_type'), 'ads');
        });

        if ($regional !== '') {
            $query->where($col('wilayah'), $regional);
        }

        if ($campus !== '') {
            $query->where(function (Builder $q) use ($col, $campus) {
                $q->where($col('unit_name'), $campus)
                    ->orWhereIn($col('partner_campus_id'), function ($sub) use ($campus) {
                        $driver = $sub->getConnection()->getDriverName();
                        $displayContains = $driver === 'sqlite'
                            ? '? LIKE (\'%\' || display_name || \'%\')'
                            : '? LIKE CONCAT(\'%\', display_name, \'%\')';
                        $nameContains = $driver === 'sqlite'
                            ? '? LIKE (\'%\' || name || \'%\')'
                            : '? LIKE CONCAT(\'%\', name, \'%\')';

                        $sub->select('id')->from('partner_campuses')
                            ->where('display_name', $campus)
                            ->orWhere('name', $campus)
                            ->orWhere('display_name', 'like', "%{$campus}%")
                            ->orWhere('name', 'like', "%{$campus}%")
                            ->orWhereRaw($displayContains, [$campus])
                            ->orWhereRaw($nameContains, [$campus]);
                    });
            });
        }

        return $query;
    }
}
