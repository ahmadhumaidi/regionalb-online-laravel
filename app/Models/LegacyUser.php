<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Accounts from the pre-existing "field activity tracking" app that shares
 * this database. No separate codebase for that app exists on this server
 * any more; only its tables remain, folded into this app going forward.
 */
class LegacyUser extends Model
{
    protected $table = 'users';

    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'username',
        'password_hash',
        'role',
        'regional',
        'campus',
        'partner_campus_id',
        'must_set_password',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'must_set_password' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function partnerCampus(): BelongsTo
    {
        return $this->belongsTo(PartnerCampus::class);
    }

    public function campuses(): BelongsToMany
    {
        return $this->belongsToMany(PartnerCampus::class, 'user_campuses', 'user_id', 'partner_campus_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'user_id');
    }
}
