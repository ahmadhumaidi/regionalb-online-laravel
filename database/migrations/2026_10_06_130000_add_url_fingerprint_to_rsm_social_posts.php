<?php

use App\Support\SocialPostUrl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rsm_social_posts', function (Blueprint $table): void {
            $table->string('url_fingerprint', 64)->nullable()->after('post_url');
        });

        $seen = [];
        DB::table('rsm_social_posts')
            ->whereIn('media_type', ['feed', 'reels', 'story', 'facebook'])
            ->whereNotNull('post_url')
            ->where('post_url', '<>', '')
            ->orderBy('id')
            ->get(['id', 'post_url'])
            ->each(function (object $post) use (&$seen): void {
                $fingerprint = SocialPostUrl::fingerprint((string) $post->post_url);
                if ($fingerprint === null || isset($seen[$fingerprint])) {
                    return;
                }

                DB::table('rsm_social_posts')->where('id', $post->id)->update(['url_fingerprint' => $fingerprint]);
                $seen[$fingerprint] = true;
            });

        Schema::table('rsm_social_posts', function (Blueprint $table): void {
            $table->unique('url_fingerprint', 'uq_rsm_social_posts_url_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('rsm_social_posts', function (Blueprint $table): void {
            $table->dropUnique('uq_rsm_social_posts_url_fingerprint');
            $table->dropColumn('url_fingerprint');
        });
    }
};
