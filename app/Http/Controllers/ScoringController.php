<?php

namespace App\Http\Controllers;

use App\Exports\ScoringExport;
use App\Models\RsmUser;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Services\Dashboard\ScoringTableService;
use App\Services\Dashboard\StaffJourneyService;
use App\Support\RsmRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Activity overview and download using the Staff Journey activity sources. */
class ScoringController extends Controller
{
    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        abort_unless(RsmRole::canViewScoringTable($user), 403);
        $area = $user->viewingArea();

        $filters = DashboardFilters::fromRequest($request, 'scoring');
        $referenceOptions = ReferenceOptionsService::build($area, $user);

        $table = ScoringTableService::build($area, $filters, $user);

        return view('scoring.index', [
            'active' => 'scoring',
            'filters' => $filters,
            'referenceOptions' => $referenceOptions,
            'activities' => StaffJourneyService::ACTIVITY_LABELS,
            'rows' => StaffJourneyService::reportRows($table['rows'], $filters),
            'syncedAt' => $table['synced_at'],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        abort_unless(RsmRole::canViewScoringTable($user), 403);

        $area = $user->viewingArea();
        $filters = DashboardFilters::fromRequest($request, 'scoring');
        $table = ScoringTableService::build($area, $filters, $user);

        $table['rows'] = StaffJourneyService::reportRows($table['rows'], $filters);
        $activities = [];
        foreach (StaffJourneyService::ACTIVITY_LABELS as $key => $label) {
            $activities[$key] = ['label' => $label, 'metric_key' => $key];
        }

        return Excel::download(
            new ScoringExport($area, $filters, $table, $activities),
            'laporan-scoring-'.$filters['date_from'].'_'.$filters['date_to'].'.xlsx'
        );
    }
}
