<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RsmForumComment extends Model
{
    protected $table = 'rsm_forum_comments';

    public const UPDATED_AT = null;

    protected $fillable = [
        'post_id',
        'user_id',
        'body',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RsmUser::class, 'user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(RsmForumPost::class, 'post_id');
    }
}
