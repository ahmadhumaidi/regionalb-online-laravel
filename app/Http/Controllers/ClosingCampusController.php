<?php
namespace App\Http\Controllers;
use App\Services\Dashboard\CollabMetricsService;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\ReferenceOptionsService;
use App\Support\AreaRegionals;
use Illuminate\Http\Request;
class ClosingCampusController extends Controller
{
    public function index(Request $request){$user=$request->user();$area=$user->area?:'Regional';$filters=DashboardFilters::fromRequest($request,'closing-kampus');$campus=CollabMetricsService::campusTotals($filters,$area,$user);$grouped=[];foreach($campus['rows'] as $row)$grouped[$row['regional']][]=$row;return view('closing-campus.index',['area'=>$area,'filters'=>$filters,'campus'=>$campus,'grouped'=>$grouped,'regionals'=>AreaRegionals::forArea($area),'references'=>ReferenceOptionsService::build($area,$user)]);}
}
