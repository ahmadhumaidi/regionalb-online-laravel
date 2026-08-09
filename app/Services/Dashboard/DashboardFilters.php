<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Http\Request;

/** Ports rsm_dashboard_filters_from_request() (rsm_db.php:4306). */
class DashboardFilters
{
    /** @return array{date_from: string, date_to: string, wilayah: string, unit_name: string, staff_name: string} */
    public static function fromRequest(Request $request, string $page = 'dashboard'): array
    {
        $today = Carbon::today('Asia/Jakarta');
        $defaultFrom = in_array($page, ['dashboard', 'anggaran', 'rekap', 'pencapaian', 'closing-kampus'], true)
            ? $today->copy()->startOfMonth()
            : $today->copy();

        $dateFrom = self::parseDate((string) $request->query('date_from', ''), $defaultFrom);
        $dateTo = self::parseDate((string) $request->query('date_to', ''), $today);

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $periode = (string) $request->query('periode', '');
        if (in_array($periode, ['daily', 'weekly', 'monthly'], true)) {
            [$dateFrom, $dateTo] = match ($periode) {
                'daily' => [$today->copy(), $today->copy()],
                'weekly' => [$dateFrom->copy()->startOfWeek(), $dateFrom->copy()->endOfWeek()],
                'monthly' => [$dateFrom->copy()->startOfMonth(), $dateFrom->copy()->endOfMonth()],
            };
        }

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'wilayah' => trim((string) $request->query('wilayah', '')),
            'unit_name' => trim((string) $request->query('unit_name', '')),
            'staff_name' => trim((string) $request->query('staff_name', '')),
        ];
    }

    private static function parseDate(string $value, Carbon $default): Carbon
    {
        if ($value === '') {
            return $default;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return $default;
        }
    }
}
