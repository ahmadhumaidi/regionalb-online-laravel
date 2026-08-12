<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One row per (post, user) like on a Forum Diskusi post — see ForumController::toggleLike(). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_forum_likes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->unsignedInteger('user_id');
            $table->dateTime('created_at')->useCurrent();

            $table->unique(['post_id', 'user_id'], 'uq_rsm_forum_likes');
            $table->foreign('post_id', 'fk_rsm_forum_likes_post')
                ->references('id')->on('rsm_forum_posts')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_rsm_forum_likes_user')
                ->references('id')->on('rsm_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_forum_likes');
    }
};
