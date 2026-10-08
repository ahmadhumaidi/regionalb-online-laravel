<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmSeniorActivityReport extends Model
{
    protected $table = 'rsm_senior_activity_reports';

    protected $fillable = [
        'area', 'user_id', 'activity_date', 'activity_type', 'title', 'location',
        'participants', 'agenda', 'result_text', 'next_action', 'attachment_path',
    ];

    protected function casts(): array
    {
        return ['activity_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(RsmUser::class, 'user_id');
    }
}
