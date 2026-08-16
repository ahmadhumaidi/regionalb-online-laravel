<?php

namespace App\Http\Controllers;

use App\Models\RsmForumComment;
use App\Models\RsmForumLike;
use App\Models\RsmForumPost;
use App\Services\NotificationService;
use App\Support\RsmRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Company-wide discussion feed ("Forum Diskusi") — deliberately not scoped
 * by area/role/regional like the rest of this app; every employee posts
 * into and sees the same single feed.
 */
class ForumController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();
        $highlightPostId = (int) $request->query('post', 0);

        $posts = RsmForumPost::query()
            ->with(['user', 'comments.user'])
            ->withCount('likes')
            ->when($highlightPostId > 0, fn ($query) => $query->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$highlightPostId]))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $likedPostIds = RsmForumLike::where('user_id', $user->id)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('post_id')
            ->all();

        return view('forum.index', compact('posts', 'likedPostIds', 'highlightPostId'));
    }

    public function storePost(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (blank($data['body'] ?? null) && ! $request->hasFile('image')) {
            throw ValidationException::withMessages(['body' => 'Tulis sesuatu atau lampirkan gambar.']);
        }

        RsmForumPost::create([
            'user_id' => Auth::id(),
            'body' => $data['body'] ?? null,
            'image_path' => $request->hasFile('image') ? $request->file('image')->store('forum', 'public') : null,
        ]);

        return back()->with('notice', 'Update berhasil diposting.');
    }

    /** Single endpoint toggles like/unlike based on whether a like row already exists for (post, user). */
    public function toggleLike(RsmForumPost $post): RedirectResponse
    {
        $like = RsmForumLike::where('post_id', $post->id)->where('user_id', Auth::id())->first();

        if ($like) {
            $like->delete();
        } else {
            RsmForumLike::create(['post_id' => $post->id, 'user_id' => Auth::id()]);
            NotificationService::notifyForumLike($post, Auth::user());
        }

        return back();
    }

    public function storeComment(Request $request, RsmForumPost $post): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);

        RsmForumComment::create([
            'post_id' => $post->id,
            'user_id' => Auth::id(),
            'body' => $data['body'],
        ]);
        NotificationService::notifyForumComment($post, Auth::user());

        return back()->with('notice', 'Komentar ditambahkan.');
    }

    /** Moderation (super_user only) - edits a post's text; the image, if any, is left as-is. */
    public function updatePost(Request $request, RsmForumPost $post): RedirectResponse
    {
        abort_unless(RsmRole::canModerateForum(Auth::user()), 403);

        $data = $request->validate(['body' => ['nullable', 'string', 'max:2000']]);

        if (blank($data['body'] ?? null) && ! $post->image_path) {
            throw ValidationException::withMessages(['body' => 'Tulis sesuatu atau lampirkan gambar.']);
        }

        $post->update(['body' => $data['body'] ?? null]);

        return back()->with('notice', 'Postingan diperbarui.');
    }

    public function destroyPost(RsmForumPost $post): RedirectResponse
    {
        abort_unless(RsmRole::canModerateForum(Auth::user()), 403);

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }
        $post->delete();

        return back()->with('notice', 'Postingan dihapus.');
    }

    public function updateComment(Request $request, RsmForumComment $comment): RedirectResponse
    {
        abort_unless(RsmRole::canModerateForum(Auth::user()), 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:500']]);
        $comment->update(['body' => $data['body']]);

        return back()->with('notice', 'Komentar diperbarui.');
    }

    public function destroyComment(RsmForumComment $comment): RedirectResponse
    {
        abort_unless(RsmRole::canModerateForum(Auth::user()), 403);

        $comment->delete();

        return back()->with('notice', 'Komentar dihapus.');
    }
}
