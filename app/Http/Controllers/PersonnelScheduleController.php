<?php
namespace App\Http\Controllers;
use App\Services\PersonnelScheduleService;
use App\Support\RsmRole;
use Illuminate\Http\Request;
class PersonnelScheduleController extends Controller
{
    public function index(Request $request) { abort_unless(RsmRole::canSyncCollab($request->user()), 403); $snapshot = PersonnelScheduleService::snapshot(); $zone = $snapshot['zonas'][2] ?? $snapshot['zonas']['2'] ?? []; return view('personnel-schedule.index', compact('snapshot', 'zone')); }
    public function sync(Request $request) { abort_unless(RsmRole::canSyncCollab($request->user()), 403); PersonnelScheduleService::sync(); return redirect()->route('jadwal-personalia')->with('status', 'Jadwal berhasil disinkronkan.'); }
}
