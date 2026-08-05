<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmActivityLog extends Model
{
    protected $table = 'rsm_activity_logs';

    public const UPDATED_AT = null;

    protected $fillable = [
        'report_id',
        'area',
        'actor_user_id',
        'actor_role',
        'actor_actual_role',
        'actor_view_role',
        'actor_name',
        'impersonator_user_id',
        'impersonator_name',
        'action_name',
        'old_status',
        'new_status',
        'note',
        'ip_address',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(RsmReport::class, 'report_id');
    }
}
