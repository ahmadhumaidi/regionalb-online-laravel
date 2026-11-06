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
            'database/migrations/2026_10_20_000000_expand_forum_comment_body.php',
            'database/migrations/2026_10_20_010000_add_parent_id_to_forum_comments.php',
            'database/migrations/2026_10_20_020000_add_forum_read_cursors_to_users.php',
            'database/migrations/2026_10_20_030000_create_forum_comment_likes_table.php',
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

    public function test_long_comments_can_be_created_and_edited(): void
    {
        $this->migrate();
        $owner = $this->user(910010, 'Moderator');
        $owner->update(['role' => 'super_user']);
        $post = RsmForumPost::create(['user_id' => $owner->id, 'body' => 'Diskusi']);
        $body = str_repeat("Komentar panjang.\n", 100);

        $this->actingAs($owner)
            ->post(route('forum.posts.comments.store', $post), ['body' => $body])
            ->assertSessionHasNoErrors();

        $comment = $post->comments()->firstOrFail();
        $this->assertSame(trim($body), $comment->body);

        $editedBody = str_repeat('Komentar diperbarui. ', 100);
        $this->patch(route('forum.comments.update', $comment), ['body' => $editedBody])
            ->assertSessionHasNoErrors();
        $this->assertSame(trim($editedBody), $comment->fresh()->body);
    }

    public function test_replies_reference_same_post_and_survive_parent_deletion(): void
    {
        $this->migrate();
        $owner = $this->user(910020, 'Pembalas');
        $post = RsmForumPost::create(['user_id' => $owner->id, 'body' => 'Diskusi balasan']);
        $parent = $post->comments()->create(['user_id' => $owner->id, 'body' => 'Komentar asal']);

        $this->actingAs($owner)->post(route('forum.posts.comments.store', $post), [
            'body' => 'Ini balasan', 'parent_id' => $parent->id,
        ])->assertSessionHasNoErrors();
        $reply = $post->comments()->where('parent_id', $parent->id)->firstOrFail();
        $this->get(route('forum'))->assertOk()->assertSee('Membalas Pembalas')->assertSee('forum-comment-'.$parent->id);

        $other = RsmForumPost::create(['user_id' => $owner->id, 'body' => 'Postingan lain']);
        $this->post(route('forum.posts.comments.store', $other), [
            'body' => 'Tidak valid', 'parent_id' => $parent->id,
        ])->assertSessionHasErrors('parent_id');
        $this->assertSame(0, $other->comments()->count());

        $parent->delete();
        $this->assertNull($reply->fresh()->parent_id);
        $this->assertSame('Ini balasan', $reply->fresh()->body);
    }

    public function test_forum_activity_badge_counts_other_users_and_clears_only_for_reader(): void
    {
        $this->migrate();
        $reader = $this->user(910030, 'Pembaca');
        $otherReader = $this->user(910031, 'Pembaca Lain');
        $actor = $this->user(910032, 'Penulis');
        $this->actingAs($actor)->post(route('forum.posts.store'), ['body' => 'Postingan terbaru'])->assertSessionHasNoErrors();
        $post = RsmForumPost::latest('id')->firstOrFail();
        $this->post(route('forum.posts.comments.store', $post), ['body' => 'Komentar terbaru'])->assertSessionHasNoErrors();
        $this->getJson(route('forum.activity'))->assertExactJson(['posts' => 0, 'comments' => 0, 'total' => 0]);

        $this->actingAs($reader)->getJson(route('forum.activity'))
            ->assertExactJson(['posts' => 1, 'comments' => 1, 'total' => 2]);
        // Polling does not acknowledge unread activity.
        $this->getJson(route('forum.activity'))->assertJsonPath('total', 2);
        $this->get(route('forum'))->assertOk()->assertSee('data-forum-activity', false);
        $this->getJson(route('forum.activity'))->assertJsonPath('total', 0);
        $this->actingAs($otherReader)->getJson(route('forum.activity'))->assertJsonPath('total', 2);

        $this->actingAs($actor)->post(route('forum.posts.comments.store', $post), ['body' => 'Tambahan baru']);
        $this->actingAs($reader)->getJson(route('forum.activity'))
            ->assertExactJson(['posts' => 0, 'comments' => 1, 'total' => 1]);
    }

    public function test_forum_lists_likers_and_removes_them_after_unlike(): void
    {
        $this->migrate();
        $owner = $this->user(910040, 'Pemilik');
        $actor = $this->user(910041, 'Penyuka Unik');
        $post = RsmForumPost::create(['user_id' => $owner->id, 'body' => 'Disukai']);
        $this->actingAs($actor)->post(route('forum.posts.like', $post));
        $this->actingAs($owner)->get(route('forum'))
            ->assertOk()->assertSee('Disukai oleh (1)')->assertSee('<li>Penyuka Unik</li>', false);
        $this->actingAs($actor)->post(route('forum.posts.like', $post));
        $this->actingAs($owner)->get(route('forum'))
            ->assertOk()->assertSee('Disukai oleh (0)')->assertDontSee('<li>Penyuka Unik</li>', false);
    }

    public function test_comment_likes_toggle_independently_and_cascade_on_deletion(): void
    {
        $this->migrate();
        $owner = $this->user(910050, 'Pemilik Komentar');
        $actor = $this->user(910051, 'Penyuka Komentar');
        $post = RsmForumPost::create(['user_id' => $owner->id, 'body' => 'Diskusi']);
        $comment = $post->comments()->create(['user_id' => $owner->id, 'body' => 'Komentar disukai']);
        $reply = $post->comments()->create(['user_id' => $owner->id, 'body' => 'Balasan', 'parent_id' => $comment->id]);
        $this->actingAs($actor)->post(route('forum.comments.like', $comment))->assertRedirect();
        $this->post(route('forum.comments.like', $reply))->assertRedirect();
        $this->actingAs($owner)->post(route('forum.comments.like', $comment))->assertRedirect();
        $this->assertSame(2, $comment->likes()->count());
        $this->get(route('forum'))->assertOk()->assertSee('Yang menyukai komentar (2)')->assertSee('<li>Penyuka Komentar</li>', false);
        $this->actingAs($actor)->post(route('forum.comments.like', $comment));
        $this->assertSame(1, $comment->likes()->count());
        $this->assertSame(1, $reply->likes()->count());
        $this->assertSame(0, $post->likes()->count());
        $comment->delete();
        $this->assertDatabaseMissing('rsm_forum_comment_likes', ['comment_id' => $comment->id]);
        $reply->delete();
        $this->assertDatabaseCount('rsm_forum_comment_likes', 0);
        $this->post(route('forum.comments.like', ['comment' => 999999]))->assertNotFound();
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
