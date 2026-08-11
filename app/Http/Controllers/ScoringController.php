<?php

namespace App\Http\Controllers;

use App\Models\RsmUser;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Services\Dashboard\ScoringTableService;
use App\Support\RsmRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Scoring" menu: one wide table of every staff member with every
 * assessment indicator already tracked in the system as its own column
 * (registrasi/herreg personal & kampus, laporan iklan, follow up, leads,
 * dll). Point weighting per indicator is a deliberately separate follow-up
 * (see ScoringTableService docblock).
 */
class ScoringController extends Controller
{
    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        abort_unless(RsmRole::canViewScoringTable($user), 403);
        $area = $user->area ?: 'Regional B';

        $filters = DashboardFilters::fromRequest($request, 'scoring');
        $referenceOptions = ReferenceOptionsService::build($area, $user);

        $table = ScoringTableService::build($area, $filters, $user);

        return view('scoring.index', [
            'active' => 'scoring',
            'filters' => $filters,
            'referenceOptions' => $referenceOptions,
            'indicators' => (array) config('scoring_indicators.indicators', []),
            'rows' => $table['rows'],
            'syncedAt' => $table['synced_at'],
        ]);
    }
}
