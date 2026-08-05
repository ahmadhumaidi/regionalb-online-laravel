<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pivot for users <-> partner_campuses. Composite primary key
 * (user_id, partner_campus_id) — not natively supported by Eloquent, so
 * this model is queried via explicit where() calls rather than find().
 */
class UserCampus extends Model
{
    protected $table = 'user_campuses';

    public $incrementing = false;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'partner_campus_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
