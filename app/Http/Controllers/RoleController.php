<?php
namespace App\Http\Controllers;
use App\Models\RsmActivityLog;
use App\Models\RsmUser;
use App\Support\RsmRole;
use Illuminate\Http\Request;
class RoleController extends Controller
{
    public function index(Request $request){$roles=[];$points=['super_user'=>['Akses penuh seluruh sistem','Bisa masuk sebagai semua user','Mengelola user, target, kampus, dan approval','Melihat semua laporan dan export'],'executive_director'=>['Melihat semua wilayah, unit, dan staff','Menyetujui atau menolak laporan','Mengatur target pencapaian staff','Export semua laporan'],'director'=>['Melihat semua wilayah, unit, dan staff','Menyetujui atau menolak laporan','Mengatur target pencapaian staff','Export semua laporan'],'senior'=>['Melihat semua wilayah, unit, dan staff','Menyetujui atau menolak laporan','Melihat seluruh anggaran iklan','Export semua laporan'],'mentor'=>['Melihat semua wilayah, unit, dan staff','Mengatur target pencapaian staff'],'koordinator'=>['Melihat data wilayahnya saja','Mengajukan dan memverifikasi laporan wilayah','Menambahkan kegiatan wilayah','Membuat rekap wilayah'],'staff'=>['Menambahkan laporan kegiatan','Melihat laporan milik sendiri','Edit hanya saat Draft atau Revisi','Tidak melihat wilayah lain']];foreach(RsmRole::ROLE_LABELS as $key=>$label)$roles[]=['key'=>$key,'label'=>$label,'points'=>$points[$key]??[]];$user=$request->user();$logsQuery=RsmActivityLog::query()->latest('id');if($user->role==='staff')$logsQuery->where('actor_user_id',$user->id);if($user->role===RsmUser::ROLE_KOORDINATOR&&trim((string)$user->regional)!==''){$subordinateIds=RsmUser::query()->where('role',RsmUser::ROLE_STAFF)->where('regional',$user->regional)->pluck('id');$logsQuery->where(function($q)use($user,$subordinateIds){$q->where('actor_user_id',$user->id)->orWhereIn('actor_user_id',$subordinateIds);});}$logs=$logsQuery->limit(40)->get();return view('role.index',compact('roles','logs'));}
}
