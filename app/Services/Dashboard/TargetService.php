<?php

namespace App\Services\Dashboard;

use App\Models\RsmMonthlyTarget;
use App\Models\RsmUser;

/** Ports rsm_dashboard_target() (rsm_db.php:2870). */
class TargetService
{
    public static function build(string $area, array $filters, RsmUser $user): ?array
    {
        $targetMonth = substr($filters['date_from'] ?: now()->toDateString(), 0, 7);

        $query = RsmMonthlyTarget::query()
            ->where('area', $area)
            ->where('target_month', $targetMonth)
            ->where('scope_type', 'staff');

        if ($user->role === 'staff') {
            $query->where('staff_name', $user->name);
        } elseif ($user->role === 'koordinator' && trim((string) $user->regional) !== '') {
            $query->where('wilayah', $user->regional);
        }

        if ($filters['wilayah'] !== '') {
            $query->where('wilayah', $filters['wilayah']);
        }
        if ($filters['unit_name'] !== '') {
            $query->where('unit_name', $filters['unit_name']);
        }
        if ($filters['staff_name'] !== '') {
            $query->where('staff_name', $filters['staff_name']);
        }

        $aggregate = $query->selectRaw(
            'COUNT(*) as staff_target_count, SUM(target_leads) as target_leads, '
            .'SUM(target_follow_up) as target_follow_up, SUM(target_registrasi) as target_registrasi, '
            .'SUM(target_herregistrasi) as target_herregistrasi, SUM(target_anggaran) as target_anggaran'
        )->first();

        if (! $aggregate || (int) $aggregate->staff_target_count === 0) {
            return null;
        }

        return [
            'staff_target_count' => (int) $aggregate->staff_target_count,
            'target_leads' => (float) $aggregate->target_leads,
            'target_follow_up' => (float) $aggregate->target_follow_up,
            'target_registrasi' => (float) $aggregate->target_registrasi,
            'target_herregistrasi' => (float) $aggregate->target_herregistrasi,
            'target_anggaran' => (float) $aggregate->target_anggaran,
            'target_month' => $targetMonth,
            'scope_type' => 'staff_aggregate',
        ];
    }
}
