<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmNotification extends Model
{
    protected $table = 'rsm_notifications';

    protected $fillable = [
        'area',
        'recipient_user_id',
        'report_id',
        'type',
        'title',
        'message',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(RsmReport::class, 'report_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(RsmUser::class, 'recipient_user_id');
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}
