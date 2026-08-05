<?php

namespace App\Services\Dashboard;

use App\Models\RsmUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports rsm_reference_options() (rsm_db.php:1459) for the dashboard filter
 * form's dropdowns. A staff user gets no real choices — the form shows
 * their own regional/campus/name locked, matching legacy's staff_identity_value().
 */
class ReferenceOptionsService
{
    /** @return array{regionals: list<string>, campuses: list<array{id: ?int, label: string}>, staff: list<array{id: int, name: string}>} */
    public static function build(string $area, RsmUser $user): array
    {
        if ($user->role === 'staff') {
            return [
                'regionals' => array_filter([$user->regional]),
                'campuses' => $user->campus_name ? [['id' => null, 'label' => $user->campus_name]] : [],
                'staff' => [['id' => $user->id, 'name' => $user->name]],
            ];
        }

        $regionalsQuery = RsmUser::query()
            ->where('role', 'staff')
            ->where('is_active', true)
            ->whereNotNull('regional');

        if ($user->role === 'koordinator' && trim((string) $user->regional) !== '') {
            $regionalsQuery->where('regional', $user->regional);
        }

        $regionals = $regionalsQuery->distinct()->orderBy('regional')->pluck('regional')->all();

        $staffQuery = RsmUser::query()
            ->where('role', 'staff')
            ->where('is_active', true);
        if ($regionals !== []) {
            $staffQuery->whereIn('regional', $regionals);
        }
        $staff = $staffQuery->orderBy('regional')->orderBy('name')
            ->get(['id', 'name', 'username', 'regional', 'campus_name'])
            ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name])
            ->all();

        $campuses = DB::table('partner_campuses')
            ->select('id')
            ->selectRaw('COALESCE(display_name, name) as label')
            ->orderByRaw('COALESCE(display_name, name)')
            ->get()
            ->unique('label')
            ->map(fn ($row) => ['id' => $row->id, 'label' => $row->label])
            ->values()
            ->all();

        return [
            'regionals' => $regionals,
            'campuses' => $campuses,
            'staff' => $staff,
        ];
    }
}
