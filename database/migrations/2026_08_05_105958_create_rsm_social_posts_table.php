<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rsm_social_posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id');
            $table->string('area', 40);
            $table->date('post_date');
            $table->enum('media_type', ['no_post', 'feed', 'reels', 'story']);
            $table->time('post_time')->nullable();
            $table->text('caption')->nullable();
            $table->string('post_url', 500)->nullable();
            $table->boolean('keyword_match')->default(false);
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('reach_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->string('source_name', 60)->default('Manual');
            $table->unsignedInteger('created_by_user_id')->nullable();
            $table->string('created_by_name', 180)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['area', 'post_date'], 'idx_rsm_social_posts_date');
            $table->index('account_id', 'idx_rsm_social_posts_account');
            $table->foreign('account_id', 'fk_rsm_social_posts_account')
                ->references('id')->on('rsm_social_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rsm_social_posts');
    }
};
