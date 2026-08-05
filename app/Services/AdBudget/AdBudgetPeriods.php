<?php

namespace App\Services\AdBudget;

use Carbon\Carbon;

/**
 * Ports rsm_ad_period_options()/rsm_default_ad_period() (rsm_db.php:552-572).
 * "Periode Iklan" (`ad_period`) is an Indonesian month label like "Juli
 * 2026" stored as free text on rsm_reports, not a real date column.
 */
class AdBudgetPeriods
{
    private const MONTHS_ID = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public static function label(int $year, int $month): string
    {
        return self::MONTHS_ID[$month].' '.$year;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        $options = [];
        $cursor = Carbon::now()->startOfMonth()->addMonthNoOverflow();

        for ($i = 0; $i < 13; $i++) {
            $label = self::label((int) $cursor->year, (int) $cursor->month);
            $options[] = ['value' => $label, 'label' => $label];
            $cursor = $cursor->subMonthNoOverflow();
        }

        return $options;
    }

    public static function default(?string $dateFrom = null): string
    {
        $date = $dateFrom ? Carbon::parse($dateFrom) : Carbon::now();

        return self::label((int) $date->year, (int) $date->month);
    }
}
