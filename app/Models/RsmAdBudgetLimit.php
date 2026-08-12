<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsmAdBudgetLimit extends Model
{
    protected $table = 'rsm_ad_budget_limits';

    protected $fillable = [
        'area',
        'ad_period',
        'wilayah',
        'unit_name',
        'budget_limit',
        'notes',
        'created_by_user_id',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'budget_limit' => 'decimal:2',
        ];
    }
}
