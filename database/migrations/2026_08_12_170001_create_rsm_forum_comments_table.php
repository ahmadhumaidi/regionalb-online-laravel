<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comments on a Forum Diskusi post — see ForumController::storeComment(). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_forum_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->unsignedInteger('user_id');
            $table->string('body', 500);
            $table->dateTime('created_at')->useCurrent();

            $table->index('post_id', 'idx_rsm_forum_comments_post');
            $table->foreign('post_id', 'fk_rsm_forum_comments_post')
                ->references('id')->on('rsm_forum_posts')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_rsm_forum_comments_user')
                ->references('id')->on('rsm_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_forum_comments');
    }
};
