<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmSocialPost extends Model
{
    protected $table = 'rsm_social_posts';

    protected $fillable = [
        'account_id',
        'area',
        'post_date',
        'media_type',
        'post_time',
        'caption',
        'post_url',
        'keyword_match',
        'score',
        'reach_count',
        'like_count',
        'comment_count',
        'source_name',
        'created_by_user_id',
        'created_by_name',
    ];

    protected function casts(): array
    {
        return [
            'post_date' => 'date',
            'keyword_match' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(RsmSocialAccount::class, 'account_id');
    }
}
