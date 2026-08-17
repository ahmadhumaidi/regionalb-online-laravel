<?php

namespace App\Services\Crm;

use App\Models\RsmUser;
use Illuminate\Database\Eloquent\Builder;

/**
 * Row-level visibility for rsm_crm_leads, same tiering as ReportScope
 * (app/Services/Dashboard/ReportScope.php) but simpler: leads aren't
 * scoped by campus_name/staff_name matching, just by explicit
 * owner_user_id (staff) / wilayah (koordinator). A lead with both fields
 * NULL (e.g. freshly captured by WhatsAppWebhookController) is invisible
 * to staff/koordinator by construction — only isFullAccessRole() sees it,
 * until someone in that tier assigns wilayah/owner via CrmLeadController::update().
 */
class CrmLeadScope
{
    private const FULL_ACCESS_ROLES = ['super_user', 'executive_director', 'director', 'senior', 'mentor'];

    public static function apply(Builder $query, RsmUser $user): Builder
    {
        return match (true) {
            self::isFullAccessRole($user) => $query,
            $user->role === 'koordinator' => trim((string) $user->regional) !== ''
                ? $query->where('wilayah', $user->regional)
                : $query->whereRaw('1 = 0'),
            $user->role === 'staff' => $query->where('owner_user_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** Whether $user may edit/update-status/delete $lead, mirrors CoordinatorScheduleController::scope(). */
    public static function canManage(RsmUser $user, string $leadWilayah, ?int $leadOwnerId): bool
    {
        return match (true) {
            self::isFullAccessRole($user) => true,
            $user->role === 'koordinator' => trim((string) $user->regional) !== '' && $leadWilayah === $user->regional,
            $user->role === 'staff' => $leadOwnerId === $user->id,
            default => false,
        };
    }

    /** super_user/executive_director/director/senior/mentor — also the only tier allowed to (re)assign wilayah/owner on a lead. */
    public static function isFullAccessRole(RsmUser $user): bool
    {
        return in_array($user->role, self::FULL_ACCESS_ROLES, true);
    }
}
