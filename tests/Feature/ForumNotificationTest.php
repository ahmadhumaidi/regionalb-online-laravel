<?php

namespace Tests\Feature;

use App\Models\RsmForumPost;
use App\Models\RsmNotification;
use App\Models\RsmUser;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ForumNotificationTest extends TestCase
{
    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_08_05_105946_create_partner_campuses_table.php',
            'database/migrations/2026_08_05_105952_create_rsm_users_table.php',
            'database/migrations/2026_08_05_105954_create_rsm_reports_table.php',
            'database/migrations/2026_08_11_090001_create_rsm_notifications_table.php',
            'database/migrations/2026_08_12_170000_create_rsm_forum_posts_table.php',
            'database/migrations/2026_08_12_170001_create_rsm_forum_comments_table.php',
            'database/migrations/2026_08_12_170002_create_rsm_forum_likes_table.php',
            'database/migrations/2026_08_16_120000_add_forum_post_id_to_rsm_notifications_table.php',
        ]]);
    }

    public function test_comment_and_like_notify_forum_post_owner(): void
    {
        $this->migrate();

        $owner = $this->user(910001, 'Nugroho');
        $actor = $this->user(910002, 'Komentator');
        $post = RsmForumPost::create([
            'user_id' => $owner->id,
            'body' => 'Update forum dari Nugroho',
        ]);

        $this->actingAs($actor)
            ->post(route('forum.posts.comments.store', $post), ['body' => 'Siap, saya bantu cek.'])
            ->assertSessionHas('notice', 'Komentar ditambahkan.');

        $this->actingAs($actor)
            ->post(route('forum.posts.like', $post))
            ->assertRedirect();

        $this->assertDatabaseHas('rsm_notifications', [
            'recipient_user_id' => $owner->id,
            'forum_post_id' => $post->id,
            'type' => 'forum_comment',
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('rsm_notifications', [
            'recipient_user_id' => $owner->id,
            'forum_post_id' => $post->id,
            'type' => 'forum_like',
            'is_read' => false,
        ]);
    }

    public function test_opening_forum_notification_marks_read_and_redirects_to_post(): void
    {
        $this->migrate();

        $owner = $this->user(910003, 'Pemilik Post');
        $actor = $this->user(910004, 'Pemberi Like');
        $post = RsmForumPost::create([
            'user_id' => $owner->id,
            'body' => 'Postingan perlu dibuka dari notifikasi',
        ]);

        $this->actingAs($actor)->post(route('forum.posts.like', $post));

        $notification = RsmNotification::where('recipient_user_id', $owner->id)->firstOrFail();

        $this->actingAs($owner)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('forum', ['post' => $post->id]).'#forum-post-'.$post->id);

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_owner_does_not_receive_notification_for_own_forum_action(): void
    {
        $this->migrate();

        $owner = $this->user(910005, 'Pemilik Sendiri');
        $post = RsmForumPost::create([
            'user_id' => $owner->id,
            'body' => 'Postingan sendiri',
        ]);

        $this->actingAs($owner)->post(route('forum.posts.like', $post));
        $this->actingAs($owner)->post(route('forum.posts.comments.store', $post), ['body' => 'Komentar sendiri']);

        $this->assertDatabaseCount('rsm_notifications', 0);
    }

    private function user(int $id, string $name): RsmUser
    {
        return RsmUser::create([
            'id' => $id,
            'name' => $name,
            'username' => 'forum_user_'.$id,
            'password_hash' => 'x',
            'role' => 'staff',
            'jabatan' => 'Staff Unit',
            'area' => 'Regional B',
            'regional' => 'Regional 6',
            'campus_name' => 'Kampus Test',
            'is_active' => true,
        ]);
    }
}
