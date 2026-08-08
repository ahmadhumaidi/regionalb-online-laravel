@props(['posts'])

@php
    $mediaLabels = ['feed' => 'Feed', 'reels' => 'Reels', 'story' => 'Story', 'no_post' => 'Belum ada postingan'];
@endphp

<section class="rounded-2xl glass-card p-5">
    <h2 class="mb-4 text-base font-semibold text-ink">Aktivitas Konten Terbaru</h2>
    @if (empty($posts))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada aktivitas konten pada periode/filter ini.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs text-ink-muted">
                        <th class="py-2 pr-3 font-medium">Tanggal</th>
                        <th class="py-2 pr-3 font-medium">Kampus</th>
                        <th class="py-2 pr-3 font-medium">Akun</th>
                        <th class="py-2 pr-3 font-medium">Jenis</th>
                        <th class="py-2 pr-3 font-medium">Caption</th>
                        <th class="py-2 text-right font-medium">Poin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($posts as $post)
                        <tr class="border-b border-border/60 last:border-0">
                            <td class="py-2 pr-3 text-ink-muted">{{ $post['post_date'] }}{{ $post['post_time'] ? ' '.$post['post_time'] : '' }}</td>
                            <td class="py-2 pr-3 text-ink">{{ $post['unit_name'] }} <span class="text-xs text-ink-muted">{{ $post['wilayah'] }}</span></td>
                            <td class="py-2 pr-3 text-ink-muted">@{{ $post['username'] }}</td>
                            <td class="py-2 pr-3 text-ink">{{ $mediaLabels[$post['media_type']] ?? $post['media_type'] }}</td>
                            <td class="py-2 pr-3 text-ink-muted">{{ \Illuminate\Support\Str::limit((string) $post['caption'], 60) ?: '-' }}</td>
                            <td class="py-2 text-right font-semibold text-ink">{{ $post['score'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
