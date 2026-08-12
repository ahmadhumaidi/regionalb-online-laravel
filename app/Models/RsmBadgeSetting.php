<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RsmBadgeSetting extends Model
{
    protected $table = 'rsm_badge_settings';

    protected $fillable = [
        'badge_key',
        'target_value',
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
        ];
    }
}
