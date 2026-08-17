<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmCrmLead extends Model
{
    protected $table = 'rsm_crm_leads';

    protected $fillable = [
        'area',
        'wilayah',
        'campus_name',
        'owner_user_id',
        'created_by_name',
        'lead_name',
        'whatsapp',
        'email',
        'major_name',
        'origin_city',
        'source',
        'status',
        'follow_up_result',
        'notes',
        'wa_message_id',
        'ctwa_clid',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(RsmUser::class, 'owner_user_id');
    }
}
