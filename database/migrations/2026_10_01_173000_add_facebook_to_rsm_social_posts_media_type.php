<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE rsm_social_posts MODIFY media_type ENUM('no_post','feed','reels','story','facebook','tiktok') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('rsm_social_posts')->where('media_type', 'facebook')->update(['media_type' => 'feed']);
            DB::statement("ALTER TABLE rsm_social_posts MODIFY media_type ENUM('no_post','feed','reels','story','tiktok') NOT NULL");
        }
    }
};
