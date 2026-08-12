<x-layouts.app title="Forum Diskusi" active="forum">
    <div class="mx-auto max-w-2xl space-y-4">
        <section class="rounded-2xl glass-card p-5">
            <form method="POST" action="{{ route('forum.posts.store') }}" enctype="multipart/form-data" class="grid gap-3">
                @csrf
                <textarea name="body" rows="3" maxlength="2000" placeholder="Apa yang Anda pikirkan?" class="rounded-lg border-border bg-surface-muted px-3 py-2 text-sm text-ink">{{ old('body') }}</textarea>
                @error('body')<p class="text-xs text-tone-red">{{ $message }}</p>@enderror
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="text-xs text-ink-muted">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Posting</button>
                </div>
                @error('image')<p class="text-xs text-tone-red">{{ $message }} (maks. 5MB, format jpg/png/webp)</p>@enderror
            </form>
        </section>

        @forelse($posts as $post)
            <section class="rounded-2xl glass-card p-5">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-sm font-semibold text-white">
                        @if($post->user?->photo_path)<img src="{{ $post->user->photoUrl() }}" alt="{{ $post->user->name }}" class="h-full w-full object-cover">@else{{ strtoupper(mb_substr($post->user->name ?? 'U', 0, 1)) }}@endif
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-ink">{{ $post->user->name ?? 'Pengguna' }}</p>
                        <p class="text-xs text-ink-muted">{{ $post->user->jabatan ?: \App\Support\RsmRole::label($post->user->role ?? '') }} · {{ $post->created_at->diffForHumans() }}</p>
                    </div>
                </div>

                @if($post->body)
                    <p class="mt-3 whitespace-pre-line text-sm text-ink">{{ $post->body }}</p>
                @endif

                @if($post->imageUrl())
                    <img src="{{ $post->imageUrl() }}" alt="" class="mt-3 max-h-96 w-full rounded-xl object-cover">
                @endif

                <div class="mt-4 flex items-center gap-4 border-t border-border pt-3 text-xs">
                    <form method="POST" action="{{ route('forum.posts.like', $post) }}" data-preserve-scroll>
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 font-semibold {{ in_array($post->id, $likedPostIds, true) ? 'text-rose-500' : 'text-ink-muted hover:text-ink' }}">
                            <x-icon name="heart" class="h-4 w-4 {{ in_array($post->id, $likedPostIds, true) ? 'fill-current' : '' }}" />
                            {{ $post->likes_count }}
                        </button>
                    </form>
                    <span class="flex items-center gap-1.5 font-semibold text-ink-muted"><x-icon name="chat" class="h-4 w-4" />{{ $post->comments->count() }}</span>
                </div>

                @if($post->comments->isNotEmpty())
                    <div class="mt-3 space-y-2 border-t border-border pt-3">
                        @foreach($post->comments as $comment)
                            <div class="flex items-start gap-2">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-muted text-xs font-semibold text-ink">
                                    @if($comment->user?->photo_path)<img src="{{ $comment->user->photoUrl() }}" alt="{{ $comment->user->name }}" class="h-full w-full object-cover">@else{{ strtoupper(mb_substr($comment->user->name ?? 'U', 0, 1)) }}@endif
                                </span>
                                <div class="min-w-0 rounded-xl bg-surface-muted px-3 py-2">
                                    <p class="text-xs font-semibold text-ink">{{ $comment->user->name ?? 'Pengguna' }}</p>
                                    <p class="text-sm text-ink">{{ $comment->body }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('forum.posts.comments.store', $post) }}" data-preserve-scroll class="mt-3 flex items-center gap-2">
                    @csrf
                    <input type="text" name="body" maxlength="500" placeholder="Tulis komentar..." required class="flex-1 rounded-lg border-border bg-surface-muted px-3 py-1.5 text-sm text-ink">
                    <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">Kirim</button>
                </form>
            </section>
        @empty
            <section class="rounded-2xl glass-card p-8 text-center">
                <p class="text-sm text-ink-muted">Belum ada update. Jadilah yang pertama memposting.</p>
            </section>
        @endforelse

        @if($posts->hasPages())
            <div class="flex items-center justify-between text-sm">
                @if($posts->previousPageUrl())
                    <a href="{{ $posts->previousPageUrl() }}" class="rounded-lg border border-border px-3 py-1.5 text-ink-muted hover:text-ink">&larr; Sebelumnya</a>
                @else
                    <span></span>
                @endif
                @if($posts->hasMorePages())
                    <a href="{{ $posts->nextPageUrl() }}" class="rounded-lg border border-border px-3 py-1.5 text-ink-muted hover:text-ink">Berikutnya &rarr;</a>
                @endif
            </div>
        @endif
    </div>
</x-layouts.app>
