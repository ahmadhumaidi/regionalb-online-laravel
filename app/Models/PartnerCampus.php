<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerCampus extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'display_name',
        'kode_kampus',
        'address',
        'longitude',
        'latitude',
        'logo_url',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(LegacyUser::class, 'partner_campus_id');
    }

    public function memberUsers(): BelongsToMany
    {
        return $this->belongsToMany(LegacyUser::class, 'user_campuses', 'partner_campus_id', 'user_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'campus_id');
    }
}
