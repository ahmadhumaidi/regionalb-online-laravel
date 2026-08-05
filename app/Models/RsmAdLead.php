<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmAdLead extends Model
{
    protected $table = 'rsm_ad_leads';

    public const UPDATED_AT = null;

    protected $fillable = [
        'report_id',
        'lead_name',
        'whatsapp',
        'email',
        'campus_name',
        'major_name',
        'origin_city',
        'follow_up_result',
        'progress_status',
        'closing_status',
        'closing_update',
        'notes',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(RsmReport::class, 'report_id');
    }
}
