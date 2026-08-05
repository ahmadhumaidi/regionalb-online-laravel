<?php

namespace App\Support;

/** Ports rsm_area_regionals(): the fixed regional list for an area, also used to order regional-grouped panels. */
class AreaRegionals
{
    /** @return list<string> */
    public static function forArea(string $area): array
    {
        return str_contains($area, 'B')
            ? ['Regional 4', 'Regional 5', 'Regional 6', 'Regional 7']
            : ['Regional 1', 'Regional 2', 'Regional 3'];
    }
}
