<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsmBdcReportUserSnapshot extends Model
{
    protected $table = 'rsm_bdc_report_user_snapshots';

    protected $fillable = [
        'snapshot_date',
        'fetched_at',
        'nik',
        'name',
        'campus_name',
        'wilayah',
        'total_count',
        'data_baru_count',
        'cold_count',
        'warm_count',
        'hot_count',
        'closing_count',
        'wawancara_count',
        'belum_herreg_count',
        'herreg_count',
        'fu_hari_ini_count',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'fetched_at' => 'datetime',
        ];
    }
}
