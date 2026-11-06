<?php

namespace App\Services;

use App\Models\RsmForumComment;
use App\Models\RsmForumPost;
use App\Models\RsmUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForumActivityService
{
    public static function available(): bool
    {
        return Schema::hasColumn('rsm_users', 'forum_read_post_id');
    }

    /** @return array{posts: int, comments: int, total: int} */
    public static function unread(RsmUser $user): array
    {
        if (! self::available()) {
            return ['posts' => 0, 'comments' => 0, 'total' => 0];
        }

        $posts = RsmForumPost::where('id', '>', (int) $user->forum_read_post_id)
            ->where('user_id', '!=', $user->id)->count();
        $comments = RsmForumComment::where('id', '>', (int) $user->forum_read_comment_id)
            ->where('user_id', '!=', $user->id)->count();

        return ['posts' => $posts, 'comments' => $comments, 'total' => $posts + $comments];
    }

    /** Capture before loading the feed so later arrivals remain unread. */
    public static function snapshot(): array
    {
        return [
            'forum_read_post_id' => (int) RsmForumPost::max('id'),
            'forum_read_comment_id' => (int) RsmForumComment::max('id'),
        ];
    }

    public static function markRead(RsmUser $user, array $snapshot): void
    {
        if (! self::available()) {
            return;
        }

        // Monotonic cursors also handle simultaneous tabs without moving backwards.
        foreach ($snapshot as $column => $id) {
            DB::table('rsm_users')->where('id', $user->id)->where($column, '<', $id)->update([$column => $id]);
            $user->setAttribute($column, max((int) $user->getAttribute($column), $id));
        }
    }
}
