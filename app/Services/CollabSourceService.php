<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;
class CollabSourceService
{
    public static function snapshot(): array
    {
        $raw=null; if(Storage::disk('local')->exists('collab_achievement.json'))$raw=json_decode(Storage::disk('local')->get('collab_achievement.json'),true); if(!is_array($raw)){ $path=base_path('../regionalb.online/public_html/runtime/cache/collab_achievement.json'); $raw=is_file($path)?json_decode((string)file_get_contents($path),true):[]; }
        $reports=[]; foreach(($raw['reports']??[]) as $name=>$report){$rows=$report['tables'][0]??[];$rows=is_array($rows)?$rows:[];$columns=0;foreach($rows as $row)if(is_array($row))$columns=max($columns,count($row));$reports[$name]=['name'=>$name,'rows'=>$rows,'row_count'=>count($rows),'column_count'=>$columns,'source_url'=>$report['source_url']??'','source_mode'=>$report['source_mode']??'','cached_at'=>$report['cached_at']??'','created_at'=>$report['created_at']??'','error'=>$report['error']??''];} return ['synced_at'=>$raw['synced_at']??'','reports'=>$reports,'errors'=>$raw['errors']??[]];
    }
    public static function sync(): array { $path=base_path('../regionalb.online/public_html/runtime/cache/collab_achievement.json'); if(is_file($path)){Storage::disk('local')->put('collab_achievement.json',(string)file_get_contents($path));} return self::snapshot(); }
}
