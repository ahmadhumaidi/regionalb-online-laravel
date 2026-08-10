<?php

namespace App\Support;

use App\Models\RsmReport;
use App\Models\RsmUser;

/**
 * Mirrors the role/permission rules that lived as inline in_array() checks
 * and the can_action()/allowed_effective_roles() helpers in the legacy
 * dashboard.php + rsm_db.php. Kept as one lookup table (not spread across
 * Gate closures) so it stays a 1:1 diffable port of the legacy source.
 */
class RsmRole
{
    private const EFFECTIVE_ROLES = [
        'super_user' => ['super_user', 'executive_director', 'director', 'senior', 'mentor', 'koordinator', 'staff'],
        'executive_director' => ['executive_director', 'director', 'senior', 'mentor', 'koordinator', 'staff'],
        'director' => ['director', 'senior', 'mentor', 'koordinator', 'staff'],
        'senior' => ['senior', 'mentor', 'koordinator', 'staff'],
        'mentor' => ['mentor', 'koordinator', 'staff'],
        'koordinator' => ['koordinator', 'staff'],
        'staff' => ['staff'],
    ];

    /** Mirrors dashboard.php's $roles[$key]['label'] (line 14-22). */
    public const ROLE_LABELS = [
        'super_user' => 'Super User',
        'executive_director' => 'Executive Director',
        'director' => 'Director',
        'senior' => 'Senior Manager',
        'mentor' => 'Mentor',
        'koordinator' => 'Koordinator Wilayah',
        'staff' => 'Staff Unit',
    ];

    private const REPORT_ACTIONS = [
        'super_user' => ['detail', 'setujui', 'tolak', 'revisi', 'export'],
        'executive_director' => ['detail', 'setujui', 'tolak', 'revisi', 'export'],
        'director' => ['detail', 'setujui', 'tolak', 'revisi', 'export'],
        'senior' => ['detail', 'setujui', 'tolak', 'revisi', 'export'],
        'mentor' => ['detail', 'export'],
        'koordinator' => ['detail', 'verifikasi', 'revisi', 'export-wilayah'],
        'staff' => ['detail', 'edit-draft', 'hapus-draft'],
    ];

    /** @return list<string> */
    public static function allowedEffectiveRoles(string $actualRole): array
    {
        return self::EFFECTIVE_ROLES[$actualRole] ?? ['staff'];
    }

    public static function canAction(string $role, string $action): bool
    {
        return in_array($action, self::REPORT_ACTIONS[$role] ?? [], true);
    }

    public static function canViewUsersPage(?RsmUser $user): bool
    {
        return in_array($user?->role, ['super_user', 'senior'], true);
    }

    public static function canManageTargets(?RsmUser $user): bool
    {
        return in_array($user?->role, ['super_user', 'executive_director', 'director', 'senior', 'mentor'], true);
    }

    public static function canSyncCollab(?RsmUser $user): bool
    {
        return in_array($user?->role, ['super_user', 'executive_director', 'director', 'senior', 'mentor'], true);
    }

    public static function canManageAdBudget(?RsmUser $user): bool
    {
        return in_array($user?->role, ['super_user', 'senior'], true);
    }

    /**
     * Wider than canManageAdBudget() (which gates the plafon-setting form):
     * these are the roles whose Setujui/Tolak/Revisi buttons appear on the
     * ads report table itself (action_buttons(), dashboard.php:4186-4227).
     */
    public static function canReviewAdBudgetRequest(?RsmUser $user): bool
    {
        return in_array($user?->role, ['super_user', 'executive_director', 'director', 'senior'], true);
    }

    /**
     * The "Verifikasi Laporan" step (Dilaporkan Unit → Diverifikasi), one
     * step ahead of canManageAdBudget()'s "Tandai Selesai": confirms the
     * bukti/realisasi staff or korwil reported is correct, before the
     * senior tier can close the report out. Open to Korwil (their own
     * wilayah's reports only, mirroring REPORT_ACTIONS's koordinator =>
     * 'verifikasi') and to canManageAdBudget()'s super_user/senior pairing.
     */
    public static function canVerifyAdBudgetRequest(RsmReport $report, ?RsmUser $user): bool
    {
        if (self::canManageAdBudget($user)) {
            return true;
        }

        return $user?->role === 'koordinator' && $report->wilayah === $user->regional;
    }

    /** "Scoring" menu: a supervisory tool (koordinator reviewing their wilayah, senior tier reviewing the whole area) - not meant for staff to browse their peers' indicators. */
    public static function canViewScoringTable(?RsmUser $user): bool
    {
        return $user?->role !== 'staff';
    }

    public static function canViewJadwalKoordinator(?RsmUser $user): bool
    {
        return $user?->role !== 'staff';
    }

    public static function canManageCoordinatorSchedule(?RsmUser $user): bool
    {
        return in_array($user?->role, [
            'super_user', 'executive_director', 'director', 'senior', 'mentor', 'koordinator',
        ], true);
    }

    public static function canImpersonate(?RsmUser $user): bool
    {
        return in_array($user?->role, ['super_user', 'executive_director', 'director', 'senior'], true);
    }

    public static function label(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucwords(str_replace('_', ' ', $role));
    }
}
