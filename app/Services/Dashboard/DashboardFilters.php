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
        $defaultFrom = in_array($page, ['dashboard', 'anggaran', 'rekap', 'pencapaian', 'closing-kampus', 'scoring'], true)
            ? $today->copy()->startOfMonth()
            : $today->copy();

        $dateFrom = self::parseDate((string) $request->input('date_from', ''), $defaultFrom);
        $dateTo = self::parseDate((string) $request->input('date_to', ''), $today);

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $periode = (string) $request->input('periode', '');
        if (in_array($periode, ['daily', 'yesterday', 'weekly', 'monthly'], true)) {
            [$dateFrom, $dateTo] = match ($periode) {
                'daily' => [$today->copy(), $today->copy()],
                'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
                'weekly' => [$dateFrom->copy()->startOfWeek(), $dateFrom->copy()->endOfWeek()],
                'monthly' => [$dateFrom->copy()->startOfMonth(), $dateFrom->copy()->endOfMonth()],
            };
        }

        return [
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'wilayah' => trim((string) $request->input('wilayah', '')),
            'unit_name' => trim((string) $request->input('unit_name', '')),
            'staff_name' => trim((string) $request->input('staff_name', '')),
        ];
    }

    /**
     * No date/wilayah/unit/staff restriction - used by
     * GamificationService::profileSummary() so a user's Profile page shows
     * their all-time standing, not just whatever period happens to be
     * selected on the Dashboard filter.
     *
     * @return array{date_from: string, date_to: string, wilayah: string, unit_name: string, staff_name: string}
     */
    public static function allTime(): array
    {
        return [
            'date_from' => '2000-01-01',
            // End-of-day (not just today's date) so a whereBetween upper
            // bound still includes today's reports on drivers/columns that
            // store report_date with a time component (e.g. SQLite in
            // tests) instead of a bare date.
            'date_to' => Carbon::today('Asia/Jakarta')->endOfDay()->toDateTimeString(),
            'wilayah' => '',
            'unit_name' => '',
            'staff_name' => '',
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
