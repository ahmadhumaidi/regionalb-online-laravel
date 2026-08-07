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
    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $area = $user->area ?: 'Regional B';

        return view('aktivitas.index', [
            'active' => 'aktivitas',
            'rows' => ReportListService::build(RsmReport::TYPE_OTHER, $area, $user),
        ]);
    }
}
