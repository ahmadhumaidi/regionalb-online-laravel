<?php

namespace App\Services\Dashboard;

/** Ports rsm_dashboard_percent()/rsm_dashboard_divide() (rsm_db.php:4414/4419). */
class DashboardNumbers
{
    public static function percent(float $part, float $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }

    public static function divide(float $part, float $whole): float
    {
        return $whole > 0 ? $part / $whole : 0.0;
    }
}
