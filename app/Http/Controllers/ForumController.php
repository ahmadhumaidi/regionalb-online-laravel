<?php

namespace App\Http\Controllers;

use App\Models\RsmForumComment;
use App\Models\RsmForumLike;
use App\Models\RsmForumPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Company-wide discussion feed ("Forum Diskusi") — deliberately not scoped
 * by area/role/regional like the rest of this app; every employee posts
 * into and sees the same single feed.
 */
class ForumController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $posts = RsmForumPost::query()
            ->with(['user', 'comments.user'])
            ->withCount('likes')
            ->latest()
            ->paginate(15);

        $likedPostIds = RsmForumLike::where('user_id', $user->id)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('post_id')
            ->all();

        return view('forum.index', compact('posts', 'likedPostIds'));
    }

    public function storePost(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        abort_if(blank($data['body'] ?? null) && ! $request->hasFile('image'), 422, 'Tulis sesuatu atau lampirkan gambar.');

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

        return back()->with('notice', 'Komentar ditambahkan.');
    }
}
