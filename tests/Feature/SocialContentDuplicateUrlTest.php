<?php

namespace Tests\Feature;

use App\Models\RsmSocialPost;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SocialContentDuplicateUrlTest extends TestCase
{
    public function test_staff_cannot_report_equivalent_instagram_or_facebook_links_twice(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105957_create_rsm_social_accounts_table.php',
            'database/migrations/2026_08_05_105958_create_rsm_social_posts_table.php',
            'database/migrations/2026_10_06_130000_add_url_fingerprint_to_rsm_social_posts.php',
        ]]);

        $first = RsmUser::create(['name' => 'Content Staff One', 'username' => 'content_staff_one', 'password_hash' => 'x', 'role' => RsmUser::ROLE_STAFF, 'jabatan' => 'Staff Unit', 'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'Kampus Konten', 'is_active' => true]);
        $second = RsmUser::create(['name' => 'Content Staff Two', 'username' => 'content_staff_two', 'password_hash' => 'x', 'role' => RsmUser::ROLE_STAFF, 'jabatan' => 'Staff Unit', 'area' => 'Regional B', 'regional' => 'Regional 6', 'campus_name' => 'Kampus Konten', 'is_active' => true]);
        $base = ['wilayah' => 'Regional 6', 'unit_name' => 'Kampus Konten', 'instagram_username' => 'kampuskonten'];

        $this->actingAs($first)->post(route('upload-konten-sosmed.store'), $base + [
            'post_date' => '2026-10-06',
            'post_urls' => ['feed' => 'https://www.instagram.com/p/ABC123/?utm_source=ig_web_copy_link'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($second)->post(route('upload-konten-sosmed.store'), $base + [
            'post_date' => '2026-10-07',
            'post_urls' => ['reels' => 'https://instagram.com/p/ABC123/'],
        ])->assertSessionHasErrors('post_urls.reels');

        $this->actingAs($first)->post(route('upload-konten-sosmed.store'), $base + [
            'post_date' => '2026-10-06',
            'post_urls' => ['facebook' => 'https://www.facebook.com/kampus/posts/98765/?mibextid=test'],
        ])->assertSessionHasNoErrors();
        $this->actingAs($second)->post(route('upload-konten-sosmed.store'), $base + [
            'post_date' => '2026-10-08',
            'post_urls' => ['facebook' => 'https://m.facebook.com/kampus/posts/98765/'],
        ])->assertSessionHasErrors('post_urls.facebook');

        $this->assertSame(2, RsmSocialPost::count());
    }
}
