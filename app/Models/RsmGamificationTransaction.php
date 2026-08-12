<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmGamificationTransaction extends Model
{
    protected $table = 'rsm_gamification_transactions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_type',
        'source_type',
        'source_id',
        'idempotency_key',
        'xp',
        'league_points',
        'aura_effect',
        'reason',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(RsmUser::class, 'user_id');
    }
}
