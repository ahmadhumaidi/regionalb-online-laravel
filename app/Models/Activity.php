<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historical field-visit data from the legacy activity-tracking app.
 * Not referenced by any live page/action in the current dashboard — kept
 * for data preservation, not part of any Fase 3 batch.
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'campus_id',
        'activity_type_id',
        'photo_path',
        'photo_path_2',
        'photo_path_3',
        'latitude',
        'longitude',
        'address',
        'prospect_count',
        'quantity',
        'location_status',
        'notes',
        'source_key',
        'source_nik',
        'source_staff_name',
        'source_regional',
        'source_campus_name',
        'import_source',
        'imported_by',
        'imported_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(LegacyUser::class, 'user_id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(PartnerCampus::class, 'campus_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(LegacyUser::class, 'imported_by');
    }
}
