<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Support\AreaRegionals;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClosingCampusController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $area = $user->area ?: 'Regional B';
        $filters = DashboardFilters::fromRequest($request, 'closing-kampus');
        // This page (the "Lihat semua" target from Top 5 Pencapaian Kampus)
        // is meant to list every campus in the area, with the wilayah/unit
        // selects here as an *optional* manual narrow-down - not auto-scoped
        // to the logged-in staff member's own campus the way campusTotals()
        // otherwise defaults to for role=staff.
        $scopeUser = $user->role === 'staff'
            ? (clone $user)->setRawAttributes(array_merge($user->getAttributes(), ['role' => 'senior']))
            : $user;
        $areas = count($user->accessibleAreas()) > 1 ? $user->accessibleAreas() : [$area];
        $regionals = [];
        $campusRows = collect();
        $campusOptions = collect();
        foreach ($areas as $accessibleArea) {
            $areaRegionals = AreaRegionals::forArea($accessibleArea);
            $regionals = array_merge($regionals, $areaRegionals);
            $campusOptions = $campusOptions->concat(ReferenceOptionsService::build($accessibleArea, $user)['campuses']);
            if (count($areas) > 1 && $filters['wilayah'] !== '' && ! in_array($filters['wilayah'], $areaRegionals, true)) {
                continue;
            }
            $campusRows = $campusRows->concat(CollabMetricsService::campusTotals($filters, $accessibleArea, $scopeUser)['rows']);
        }
        $campusRows = $campusRows->sortByDesc('registrasi')->values();
        $campus = ['rows' => $campusRows->all(), 'max_value' => (float) ($campusRows->max('registrasi') ?? 0)];
        $grouped = $campusRows->groupBy('regional')->map->all()->all();
        $references = ['campuses' => $campusOptions->unique('label')->sortBy('label')->values()->all()];

        return view('closing-campus.index', compact('area', 'filters', 'campus', 'grouped', 'regionals', 'references'));
    }
}
