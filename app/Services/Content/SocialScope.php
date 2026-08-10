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

    /**
     * The unit_name exact-match this used to also apply here was too
     * strict: rsm_social_accounts.unit_name is free-typed on the "Akun
     * Instagram Kampus" form and doesn't reliably match rsm_users.campus_name
     * character-for-character (same class of mismatch CampusMatcher exists
     * for). Narrowed to campus is done as a fuzzy post-fetch filter in
     * ContentSummaryService::build() instead - regional is still exact
     * since wilayah values are a fixed, consistent list.
     */
    private static function applyStaffScope(Builder $query, RsmUser $user, \Closure $col): Builder
    {
        $regional = trim((string) $user->regional);

        return $regional !== '' ? $query->where($col('wilayah'), $regional) : $query;
    }
}
