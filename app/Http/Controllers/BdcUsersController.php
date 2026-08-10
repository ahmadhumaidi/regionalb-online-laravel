<?php
namespace App\Http\Controllers;
use App\Services\BdcReportUsersService;
use App\Support\AreaRegionals;
use App\Support\CampusMatcher;
use Illuminate\Http\Request;
class BdcUsersController extends Controller
{
    public function index(Request $request){$user=$request->user();$data=BdcReportUsersService::snapshot();$allowed=AreaRegionals::forArea($user->area?:'Regional');$rows=array_values(array_filter($data['listdata']??[],fn($row)=>is_array($row)&&in_array((string)($row['wilayah']??''),$allowed,true)));
        // Staff only ever sees their own campus's BDC activity - the API's
        // "kampus" spelling doesn't always match rsm_users.campus_name, so
        // fuzzy-match the same way CollabMetricsService::campusTotals() does.
        $ownCampus=trim((string) $user->campus_name);
        if($user->role==='staff'&&$ownCampus!==''){$rows=array_values(array_filter($rows,fn($row)=>CampusMatcher::matches((string) ($row['kampus']??$row['campus_name']??''),$ownCampus)));}
        usort($rows,fn($a,$b)=>strnatcasecmp((string)($a['wilayah']??''),(string)($b['wilayah']??''))?:((float)($b['closing']??0)<=>(float)($a['closing']??0))?:strnatcasecmp((string)($a['nama']??''),(string)($b['nama']??'')));$totals=['total'=>array_sum(array_map(fn($r)=>(float)($r['total']??0),$rows)),'closing'=>array_sum(array_map(fn($r)=>(float)($r['closing']??0),$rows)),'fu_hari_ini'=>array_sum(array_map(fn($r)=>(float)($r['fu_hari_ini']??0),$rows))];return view('bdc-users.index',compact('data','rows','totals'));}
    public function refresh(Request $request){abort_unless(\App\Support\RsmRole::canSyncCollab($request->user()),403);BdcReportUsersService::refresh();return back()->with('status','Snapshot BDC berhasil disegarkan.');}
}
