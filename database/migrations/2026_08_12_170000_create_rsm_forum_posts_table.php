<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company-wide discussion feed ("Forum Diskusi") — deliberately not scoped
 * by area/role like every other table in this app; every employee posts
 * into and sees the same feed. See ForumController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_forum_posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('user_id', 'fk_rsm_forum_posts_user')
                ->references('id')->on('rsm_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_forum_posts');
    }
};
