<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class RsmForumPost extends Model
{
    protected $table = 'rsm_forum_posts';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'body',
        'image_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(RsmUser::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RsmForumComment::class, 'post_id')->oldest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(RsmForumLike::class, 'post_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }
}
