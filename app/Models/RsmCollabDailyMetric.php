<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsmCollabDailyMetric extends Model
{
    protected $table = 'rsm_collab_daily_metrics';

    public $timestamps = false;

    protected $fillable = [
        'report_name',
        'metric_date',
        'entity_key',
        'staff_nik',
        'staff_name',
        'regional',
        'campus_name',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'value' => 'decimal:2',
        ];
    }
}
