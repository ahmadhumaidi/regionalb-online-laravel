<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_forum_comment_likes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('comment_id');
            $table->unsignedInteger('user_id');
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['comment_id', 'user_id']);
            $table->foreign('comment_id')->references('id')->on('rsm_forum_comments')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('rsm_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_forum_comment_likes');
    }
};
