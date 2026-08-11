<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsmMonthlyTarget extends Model
{
    protected $table = 'rsm_monthly_targets';

    protected $fillable = [
        'area',
        'target_month',
        'scope_type',
        'scope_key',
        'wilayah',
        'unit_name',
        'staff_name',
        'target_leads',
        'target_follow_up',
        'target_registrasi',
        'target_herregistrasi',
        'target_anggaran',
        'indicator_targets',
        'notes',
        'created_by_user_id',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'target_anggaran' => 'decimal:2',
            'indicator_targets' => 'array',
        ];
    }
}
