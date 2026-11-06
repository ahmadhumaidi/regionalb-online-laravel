<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_users', function (Blueprint $table) {
            $table->unsignedInteger('forum_read_post_id')->default(0);
            $table->unsignedInteger('forum_read_comment_id')->default(0);
        });
        // Start tracking from activation, without treating historical activity as new.
        DB::table('rsm_users')->update([
            'forum_read_post_id' => DB::table('rsm_forum_posts')->max('id') ?? 0,
            'forum_read_comment_id' => DB::table('rsm_forum_comments')->max('id') ?? 0,
        ]);
    }

    public function down(): void
    {
        Schema::table('rsm_users', function (Blueprint $table) {
            $table->dropColumn(['forum_read_post_id', 'forum_read_comment_id']);
        });
    }
};
