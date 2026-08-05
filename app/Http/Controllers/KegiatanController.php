<?php

namespace App\Http\Controllers;

use App\Models\RsmReport;
use App\Models\RsmUser;
use App\Services\Reports\ReportListService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Read-only pass of "Kegiatan Marketing" (dashboard.php:1162-1181, list
 * side only). Legacy's filter bar here has no `name` attributes and isn't
 * wired to any query — it's dead UI, so nothing is lost by omitting it.
 * The "Tambah Kegiatan Marketing" create form is a separate pass (needs
 * the generic field-renderer + file upload infra Anggaran's create form
 * also still needs).
 */
class KegiatanController extends Controller
{
    public function index(Request $request): View
    {
        /** @var RsmUser $user */
        $user = Auth::user();
        $area = $user->area ?: 'Regional';

        return view('kegiatan.index', [
            'active' => 'kegiatan',
            'rows' => ReportListService::build(RsmReport::TYPE_MARKETING, $area, $user),
        ]);
    }
}
