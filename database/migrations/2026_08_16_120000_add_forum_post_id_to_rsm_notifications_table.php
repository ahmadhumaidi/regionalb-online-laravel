<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_notifications', function (Blueprint $table) {
            $table->unsignedInteger('forum_post_id')->nullable()->after('report_id');
            $table->index('forum_post_id', 'idx_rsm_notif_forum_post');
            $table->foreign('forum_post_id', 'fk_rsm_notif_forum_post')
                ->references('id')->on('rsm_forum_posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rsm_notifications', function (Blueprint $table) {
            $table->dropForeign('fk_rsm_notif_forum_post');
            $table->dropIndex('idx_rsm_notif_forum_post');
            $table->dropColumn('forum_post_id');
        });
    }
};
