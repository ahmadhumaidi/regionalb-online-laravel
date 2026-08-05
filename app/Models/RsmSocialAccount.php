<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RsmSocialAccount extends Model
{
    protected $table = 'rsm_social_accounts';

    protected $fillable = [
        'area',
        'wilayah',
        'unit_name',
        'instagram_username',
        'instagram_business_id',
        'facebook_page_id',
        'facebook_page_token',
        'token_expires_at',
        'connected_at',
        'pic_name',
        'pic_phone',
        'connection_status',
        'notes',
        'is_active',
        'created_by_user_id',
        'created_by_name',
    ];

    protected $hidden = [
        'facebook_page_token',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(RsmSocialPost::class, 'account_id');
    }
}
