<?php
namespace App\Http\Controllers;
use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Support\AreaRegionals;
use Illuminate\Http\Request;
class ClosingCampusController extends Controller
{
    public function index(Request $request){$user=$request->user();$area=$user->area?:'Regional';$filters=DashboardFilters::fromRequest($request,'closing-kampus');
        // This page (the "Lihat semua" target from Top 5 Pencapaian Kampus)
        // is meant to list every campus in the area, with the wilayah/unit
        // selects here as an *optional* manual narrow-down - not auto-scoped
        // to the logged-in staff member's own campus the way campusTotals()
        // otherwise defaults to for role=staff.
        $scopeUser=$user->role==='staff'?(clone $user)->setRawAttributes(array_merge($user->getAttributes(),['role'=>'senior'])):$user;
        $campus=CollabMetricsService::campusTotals($filters,$area,$scopeUser);$grouped=[];foreach($campus['rows'] as $row)$grouped[$row['regional']][]=$row;return view('closing-campus.index',['area'=>$area,'filters'=>$filters,'campus'=>$campus,'grouped'=>$grouped,'regionals'=>AreaRegionals::forArea($area),'references'=>ReferenceOptionsService::build($area,$user)]);}
}
