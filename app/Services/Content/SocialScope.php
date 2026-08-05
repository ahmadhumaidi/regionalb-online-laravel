<?php

namespace App\Services\Content;

use App\Models\RsmUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ports rsm_social_scope_sql() (rsm_db.php:2138) — narrower than
 * App\Services\Dashboard\ReportScope: no 'ads'-report bypass, no
 * partner_campus_id fallback join, since rsm_social_accounts/posts don't
 * carry those columns.
 */
class SocialScope
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
        $regional = trim((string) $user->regional);
        $campus = trim((string) $user->campus_name);

        if ($regional !== '') {
            $query->where($col('wilayah'), $regional);
        }
        if ($campus !== '') {
            $query->where($col('unit_name'), $campus);
        }

        return $query;
    }
}
