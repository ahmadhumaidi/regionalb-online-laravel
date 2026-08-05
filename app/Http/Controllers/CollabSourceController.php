<?php
namespace App\Http\Controllers;
use App\Services\CollabSourceService;
use App\Support\RsmRole;
use Illuminate\Http\Request;
class CollabSourceController extends Controller
{
    public function index(Request $request){abort_unless(RsmRole::canSyncCollab($request->user()),403);$snapshot=CollabSourceService::snapshot();$reports=$snapshot['reports'];$active=$request->query('report');if(!isset($reports[$active]))$active=array_key_first($reports);return view('collab-source.index',['snapshot'=>$snapshot,'reports'=>$reports,'active'=>$active,'report'=>$reports[$active]??null]);}
    public function sync(Request $request){abort_unless(RsmRole::canSyncCollab($request->user()),403);CollabSourceService::sync();return redirect()->route('sumber-collab')->with('status','Snapshot Collab berhasil disegarkan.');}
}
