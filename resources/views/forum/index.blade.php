<x-layouts.app title="Forum Diskusi" active="forum">
    @php $canModerate = \App\Support\RsmRole::canModerateForum(auth()->user()); @endphp
    <div class="mx-auto max-w-3xl space-y-4">
        @if($errors->any())
            <div class="rounded-lg border border-tone-red/30 bg-tone-red/10 px-4 py-3 text-sm text-tone-red">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-border bg-surface p-5">
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
            <section id="forum-post-{{ $post->id }}" class="scroll-mt-24 rounded-2xl border border-border bg-surface p-5 {{ ($highlightPostId ?? 0) === $post->id ? 'ring-2 ring-brand-400/70' : '' }}" x-data="{ editingPost: false, showLikes: false, replyId: null, replyName: '', replyBody: '', highlightedComment: null }">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-sm font-semibold text-white">
                            @if($post->user?->photo_path)<button type="button" @click.stop="$dispatch('profile-photo-preview', { src: @js($post->user->photoUrl()), name: @js($post->user->name) })" aria-label="Preview foto {{ $post->user->name }}" class="h-full w-full cursor-zoom-in rounded-full focus-visible:outline-2 focus-visible:outline-brand-400"><img src="{{ $post->user->photoUrl() }}" alt="{{ $post->user->name }}" class="h-full w-full object-cover"></button>@else{{ strtoupper(mb_substr($post->user->name ?? 'U', 0, 1)) }}@endif
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink">{{ $post->user->name ?? 'Pengguna' }}</p>
                            <p class="text-xs text-ink-muted">{{ $post->user->jabatan ?: \App\Support\RsmRole::label($post->user->role ?? '') }} · {{ $post->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    @if($canModerate)
                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" @click="editingPost = !editingPost" title="Edit" aria-label="Edit" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="edit" class="h-3.5 w-3.5" /></button>
                            <form method="POST" action="{{ route('forum.posts.destroy', $post) }}" data-preserve-scroll onsubmit="return confirm('Hapus postingan ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Hapus" aria-label="Hapus" class="rounded-md border border-tone-red p-1 text-tone-red"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                            </form>
                        </div>
                    @endif
                </div>

                @if($post->body)
                    <p class="mt-3 whitespace-pre-line text-sm text-ink" x-show="!editingPost">{{ $post->body }}</p>
                @endif

                @if($canModerate)
                    <form x-show="editingPost" x-cloak method="POST" action="{{ route('forum.posts.update', $post) }}" class="mt-3 grid gap-2">
                        @csrf
                        @method('PATCH')
                        <textarea name="body" rows="3" maxlength="2000" class="rounded-lg border-border bg-surface-muted px-3 py-2 text-sm text-ink">{{ $post->body }}</textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editingPost = false" class="rounded-lg border border-border px-3 py-1.5 text-xs">Batal</button>
                            <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">Simpan</button>
                        </div>
                    </form>
                @endif

                @if($post->imageUrl())
                    <a href="{{ $post->imageUrl() }}" target="_blank" rel="noopener noreferrer" class="mt-3 block rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-600" aria-label="Buka gambar postingan dalam ukuran penuh (tab baru)">
                        <img src="{{ $post->imageUrl() }}" alt="Gambar postingan forum" loading="lazy" class="max-h-96 w-full cursor-zoom-in rounded-xl object-contain">
                        <span class="mt-1 block text-xs text-ink-muted">Klik gambar untuk melihat ukuran penuh</span>
                    </a>
                @endif

                <div class="mt-4 flex items-center gap-4 border-t border-border pt-3 text-xs">
                    <form method="POST" action="{{ route('forum.posts.like', $post) }}" data-preserve-scroll>
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 font-semibold {{ in_array($post->id, $likedPostIds, true) ? 'text-rose-500' : 'text-ink-muted hover:text-ink' }}">
                            <x-icon name="heart" class="h-4 w-4 {{ in_array($post->id, $likedPostIds, true) ? 'fill-current' : '' }}" />
                        </button>
                    </form>
                    <button type="button" @click="showLikes = !showLikes" :aria-expanded="showLikes" aria-controls="forum-likes-{{ $post->id }}" class="font-semibold text-ink-muted hover:text-ink" aria-label="Lihat siapa yang menyukai postingan">{{ $post->likes_count }} suka</button>
                    <span class="flex items-center gap-1.5 font-semibold text-ink-muted"><x-icon name="chat" class="h-4 w-4" />{{ $post->comments->count() }}</span>
                </div>

                <div id="forum-likes-{{ $post->id }}" x-show="showLikes" x-cloak class="mt-3 rounded-xl border border-border bg-surface-muted p-3">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-ink">Disukai oleh ({{ $post->likes_count }})</p>
                        <button type="button" @click="showLikes = false" class="text-xs text-brand-600">Tutup</button>
                    </div>
                    <ul class="mt-2 max-h-48 space-y-1 overflow-y-auto text-sm text-ink">
                        @forelse($post->likes as $like)
                            <li>{{ $like->user->name ?? 'Pengguna' }}</li>
                        @empty
                            <li class="text-ink-muted">Belum ada yang menyukai postingan ini.</li>
                        @endforelse
                    </ul>
                </div>

                @if($post->comments->isNotEmpty())
                    <div class="mt-3 space-y-2 border-t border-border pt-3">
                        @foreach($post->comments as $comment)
                            @php
                                $commentPreview = \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $comment->body), 150);
                                $hasCommentPreview = mb_strlen($comment->body) > 150;
                            @endphp
                            <div id="forum-comment-{{ $comment->id }}" class="flex scroll-mt-24 items-start gap-2 rounded-xl" :class="(replyId === {{ $comment->id }} || highlightedComment === {{ $comment->id }}) && 'ring-2 ring-brand-400'" x-data="{ editingComment: false, expandedComment: false, showCommentLikes: false }">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-muted text-xs font-semibold text-ink">
                                    @if($comment->user?->photo_path)<button type="button" @click.stop="$dispatch('profile-photo-preview', { src: @js($comment->user->photoUrl()), name: @js($comment->user->name) })" aria-label="Preview foto {{ $comment->user->name }}" class="h-full w-full cursor-zoom-in rounded-full focus-visible:outline-2 focus-visible:outline-brand-400"><img src="{{ $comment->user->photoUrl() }}" alt="{{ $comment->user->name }}" class="h-full w-full object-cover"></button>@else{{ strtoupper(mb_substr($comment->user->name ?? 'U', 0, 1)) }}@endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2 rounded-xl bg-surface-muted px-3 py-2" x-show="!editingComment">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-semibold text-ink">{{ $comment->user->name ?? 'Pengguna' }}</p>
                                            @if($comment->parent_id && ($parentComment = $post->comments->firstWhere('id', $comment->parent_id)))
                                                <a href="#forum-comment-{{ $parentComment->id }}" @click="highlightedComment = {{ $parentComment->id }}" class="my-1 block rounded border-l-2 border-brand-400 bg-surface px-2 py-1 text-xs text-ink-muted">
                                                    <span class="block font-semibold">Membalas {{ $parentComment->user->name ?? 'Pengguna' }}</span>
                                                    {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/u', ' ', $parentComment->body), 150) }}
                                                </a>
                                            @endif
                                            @if($hasCommentPreview)
                                                <p x-show="!expandedComment" class="break-words text-sm text-ink">{{ $commentPreview }}</p>
                                                <p id="forum-comment-body-{{ $comment->id }}" x-show="expandedComment" x-cloak class="whitespace-pre-wrap break-words text-sm text-ink">{{ $comment->body }}</p>
                                                <button type="button" @click="expandedComment = !expandedComment" :aria-expanded="expandedComment" aria-expanded="false" aria-controls="forum-comment-body-{{ $comment->id }}" class="mt-1 text-xs font-semibold text-brand-600 hover:underline" x-text="expandedComment ? 'Tampilkan lebih sedikit' : 'Lihat selengkapnya'">Lihat selengkapnya</button>
                                            @else
                                                <p class="whitespace-pre-wrap break-words text-sm text-ink">{{ $comment->body }}</p>
                                            @endif
                                        </div>
                                        @if($canModerate)
                                            <div class="flex shrink-0 items-center gap-1">
                                                <button type="button" @click="editingComment = true" title="Edit" aria-label="Edit" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="edit" class="h-3 w-3" /></button>
                                                <form method="POST" action="{{ route('forum.comments.destroy', $comment) }}" data-preserve-scroll onsubmit="return confirm('Hapus komentar ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Hapus" aria-label="Hapus" class="rounded-md border border-tone-red p-1 text-tone-red"><x-icon name="trash" class="h-3 w-3" /></button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-1 flex items-center gap-3 text-xs">
                                        @php $commentLiked = $comment->likes->contains('user_id', auth()->id()); @endphp
                                        <form method="POST" action="{{ route('forum.comments.like', $comment) }}" data-preserve-scroll>
                                            @csrf
                                            <button type="submit" aria-label="{{ $commentLiked ? 'Batal menyukai komentar' : 'Sukai komentar' }}" aria-pressed="{{ $commentLiked ? 'true' : 'false' }}" class="flex items-center gap-1 font-semibold {{ $commentLiked ? 'text-rose-500' : 'text-ink-muted hover:text-ink' }}">
                                                <x-icon name="heart" class="h-3.5 w-3.5 {{ $commentLiked ? 'fill-current' : '' }}" />
                                                {{ $commentLiked ? 'Disukai' : 'Suka' }}
                                            </button>
                                        </form>
                                        <button type="button" @click="showCommentLikes = !showCommentLikes" :aria-expanded="showCommentLikes" aria-controls="forum-comment-likes-{{ $comment->id }}" class="text-ink-muted hover:text-ink">{{ $comment->likes->count() }} suka</button>
                                        <button type="button" @click="replyId = {{ $comment->id }}; replyName = @js($comment->user->name ?? 'Pengguna'); replyBody = @js($commentPreview); $nextTick(() => $refs.commentInput.focus())" class="text-xs font-semibold text-brand-600 hover:underline">Balas</button>
                                    </div>
                                    <div id="forum-comment-likes-{{ $comment->id }}" x-show="showCommentLikes" x-cloak class="mt-2 rounded-lg border border-border bg-surface-muted p-3">
                                        <p class="text-xs font-semibold text-ink">Yang menyukai komentar ({{ $comment->likes->count() }})</p>
                                        <ul class="mt-1 max-h-40 space-y-1 overflow-y-auto text-xs text-ink">
                                            @forelse($comment->likes as $commentLike)
                                                <li>{{ $commentLike->user->name ?? 'Pengguna' }}</li>
                                            @empty
                                                <li class="text-ink-muted">Belum ada yang menyukai komentar ini.</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                    @if($canModerate)
                                        <form x-show="editingComment" x-cloak method="POST" action="{{ route('forum.comments.update', $comment) }}" class="grid gap-1.5">
                                            @csrf
                                            @method('PATCH')
                                            <textarea name="body" rows="3" required class="rounded-lg border-border bg-surface-muted px-3 py-1.5 text-sm text-ink">{{ $comment->body }}</textarea>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="editingComment = false" class="rounded-lg border border-border px-2.5 py-1 text-[11px]">Batal</button>
                                                <button type="submit" class="rounded-lg bg-brand-600 px-2.5 py-1 text-[11px] font-semibold text-white">Simpan</button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div x-show="replyId !== null" x-cloak class="mt-3 flex items-start justify-between gap-2 rounded-lg border-l-2 border-brand-400 bg-surface-muted px-3 py-2 text-xs">
                    <div class="min-w-0"><p class="font-semibold text-ink">Membalas <span x-text="replyName"></span></p><p class="break-words text-ink-muted" x-text="replyBody"></p></div>
                    <button type="button" @click="replyId = null" class="shrink-0 text-brand-600">Batal</button>
                </div>
                <form method="POST" action="{{ route('forum.posts.comments.store', $post) }}" data-preserve-scroll class="mt-3 flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="parent_id" :value="replyId || ''">
                    <textarea x-ref="commentInput" name="body" rows="2" placeholder="Tulis komentar..." required class="min-w-0 flex-1 rounded-lg border-border bg-surface-muted px-3 py-1.5 text-sm text-ink"></textarea>
                    <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">Kirim</button>
                </form>
            </section>
        @empty
            <section class="rounded-2xl border border-border bg-surface p-8 text-center">
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
