<?php
namespace App\Services;
use Illuminate\Support\Facades\Storage;
class BdcReportUsersService
{
    public static function snapshot(): array { $path=base_path('../regionalb.online/public_html/runtime/cache/bdc_report_users.json');$data=is_file($path)?json_decode((string)file_get_contents($path),true):[];if(!is_array($data))$data=[];return $data; }
    public static function refresh(): array { return self::snapshot(); }
}
