<?php

namespace App\Http\Controllers;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Reports\ReportListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Read-only pass of "Aktivitas Lain" (dashboard.php:1222-1230, list side
 * only). The "Input Aktivitas Lain" create form is a separate pass — same
 * generic field-renderer + file upload infra needed by Kegiatan's and
 * Anggaran's create forms.
 */
class AktivitasController extends Controller
{
    private const OBSTACLE_ACTIVE_STATUSES = ['Dikirim', 'Ditindak Lanjuti'];

    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $area = $user->area ?: 'Regional B';

        $filters = [
            'type' => in_array($request->query('type'), ['all', 'kendala', 'other', 'marketing'], true) ? $request->query('type') : 'all',
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'staff' => (string) $request->query('staff', ''),
            'status' => (string) $request->query('status', ''),
            'action' => $request->query('action') === '1' ? '1' : '',
            'workflow' => in_array($request->query('workflow'), ['active', 'korwil', 'senior', 'done'], true)
                ? (string) $request->query('workflow')
                : '',
            'workflow' => in_array($request->query('workflow'), ['active', 'korwil', 'senior', 'done'], true)
                ? (string) $request->query('workflow')
                : '',
        ];
        $allRows = ReportListService::build([RsmReport::TYPE_MARKETING, RsmReport::TYPE_OTHER], $area, $user);
        $all = collect($allRows);
        $kendala = $all->where('has_kendala', true);
        $actionRequiredCount = $all->where('needs_action', true)->count();

        return view('aktivitas.index', [
            'active' => 'aktivitas',
            'rows' => ReportListService::build([RsmReport::TYPE_MARKETING, RsmReport::TYPE_OTHER], $area, $user, $filters),
            'filters' => $filters,
            'counts' => [
                'all' => $all->count(),
                'kendala' => $kendala->count(),
                'other' => $all->where('report_type', RsmReport::TYPE_OTHER)->where('has_kendala', false)->count(),
                'marketing' => $all->where('report_type', RsmReport::TYPE_MARKETING)->count(),
            ],
            'actionRequiredCount' => $actionRequiredCount,
            'summary' => [
                'active' => $kendala->whereIn('status', self::OBSTACLE_ACTIVE_STATUSES)->count(),
                'korwil' => $kendala->whereNull('escalated_to_role')->whereIn('status', self::OBSTACLE_ACTIVE_STATUSES)->count(),
                'senior' => $kendala->where('escalated_to_role', RsmUser::ROLE_SENIOR)->whereIn('status', self::OBSTACLE_ACTIVE_STATUSES)->count(),
                'done' => $kendala->where('status', 'Selesai')->count(),
            ],
        ]);
    }
}
