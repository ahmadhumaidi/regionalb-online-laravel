@props(['gamification'])

<section class="mb-6 rounded-2xl glass-card p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-ink">Arena Performa Staff</h2>
        <span class="text-xs text-ink-muted">Leaderboard berdasarkan total skor target &amp; bobot</span>
    </div>

    @if ($gamification['my_rank'])
        <div class="mb-4 flex items-center justify-between rounded-xl bg-brand-50 px-4 py-3">
            <div><strong class="text-sm font-semibold text-brand-700">{{ $gamification['my_rank']['name'] }}</strong> <span class="text-xs text-brand-700/70">Skor Anda</span></div>
            <strong class="text-lg font-bold text-brand-700">{{ number_format((float) $gamification['my_rank']['points'], 2, ',', '.') }} skor</strong>
        </div>
    @endif

    @if (empty($gamification['leaderboard']))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data performa staff pada periode/filter ini.</p>
    @else
        <div class="space-y-2">
            @foreach ($gamification['leaderboard'] as $i => $row)
                <div class="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-ink-muted">{{ $i + 1 }}</span>
                    <div class="min-w-0 flex-1">
                        <strong class="block truncate text-sm font-medium text-ink">{{ $row['name'] }}</strong>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($row['badges'] as $badge)
                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">{{ $badge }}</span>
                            @endforeach
                        </div>
                    </div>
                    <strong class="shrink-0 text-sm font-semibold text-ink">{{ number_format((float) $row['points'], 2, ',', '.') }} skor</strong>
                </div>
            @endforeach
        </div>
    @endif
</section>
