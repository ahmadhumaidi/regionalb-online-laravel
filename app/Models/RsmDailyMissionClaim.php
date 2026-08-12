<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmDailyMissionClaim extends Model
{
    protected $table = 'rsm_daily_mission_claims';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'mission_key',
        'claim_date',
        'energy',
        'stars',
    ];

    protected $casts = [
        'claim_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RsmUser::class, 'user_id');
    }
}
