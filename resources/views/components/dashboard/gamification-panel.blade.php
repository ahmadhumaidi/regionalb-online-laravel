@props(['gamification'])

@php
    $leaderboard = $gamification['leaderboard'] ?? [];
    $allLeaderboard = $gamification['all_leaderboard'] ?? $leaderboard;
    $movementPill = function (?int $delta, string $size = 'sm'): string {
        $base = $size === 'xs'
            ? 'rounded-full px-2 py-0.5 text-[11px] font-semibold'
            : 'rounded-full px-2.5 py-1 text-xs font-semibold';

        if ($delta === null) {
            return '<span class="'.$base.' bg-sky-50 text-sky-700">Baru</span>';
        }
        if ($delta > 0) {
            return '<span class="'.$base.' bg-emerald-50 text-emerald-700">Naik '.$delta.'</span>';
        }
        if ($delta < 0) {
            return '<span class="'.$base.' bg-rose-50 text-rose-700">Turun '.abs($delta).'</span>';
        }

        return '<span class="'.$base.' bg-surface-muted text-ink-muted">Tetap</span>';
    };
@endphp

<section class="mb-6 rounded-2xl glass-card p-5">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-ink">Arena Performa Staff</h2>
            <span class="text-xs text-ink-muted">Leaderboard berdasarkan total skor target &amp; bobot</span>
        </div>
        @if (count($allLeaderboard) > count($leaderboard))
            <button type="button" onclick="document.getElementById('arena-leaderboard-dialog').showModal()" class="inline-flex items-center justify-center rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-100">
                Lihat Semua
            </button>
        @endif
    </div>

    @if ($gamification['my_rank'])
        <div class="mb-4 flex flex-col gap-3 rounded-xl bg-brand-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <strong class="text-sm font-semibold text-brand-700">{{ $gamification['my_rank']['name'] }}</strong>
                <span class="text-xs text-brand-700/70">Skor Anda</span>
                {!! $movementPill($gamification['my_rank']['rank_delta'] ?? null, 'xs') !!}
            </div>
            <div class="flex items-center gap-3 sm:justify-end">
                <span class="text-xs font-semibold text-brand-700/70">Rank #{{ $gamification['my_rank']['rank'] ?? '-' }}</span>
                <strong class="text-lg font-bold text-brand-700">{{ number_format((float) $gamification['my_rank']['points'], 2, ',', '.') }} skor</strong>
            </div>
        </div>
    @endif

    @if (empty($leaderboard))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data performa staff pada periode/filter ini.</p>
    @else
        <div class="space-y-2">
            @foreach ($leaderboard as $row)
                <div class="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-ink-muted">{{ $row['rank'] ?? $loop->iteration }}</span>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                        @if (! empty($row['photo_path']))
                            <img src="{{ $row['photo_path'] }}" alt="{{ $row['name'] }}" class="h-full w-full object-cover">
                        @else
                            {{ strtoupper(mb_substr($row['name'] ?: 'U', 0, 1)) }}
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <strong class="block truncate text-sm font-medium text-ink">{{ $row['name'] }}</strong>
                        <div class="mt-1 flex flex-wrap gap-1">
                            @foreach ($row['badges'] as $badge)
                                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">{{ $badge }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <strong class="text-sm font-semibold text-ink">{{ number_format((float) $row['points'], 2, ',', '.') }} skor</strong>
                        {!! $movementPill($row['rank_delta'] ?? null, 'xs') !!}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <dialog id="arena-leaderboard-dialog" class="schedule-dialog arena-leaderboard-dialog" onclick="if (event.target === this) this.close()">
        <form method="dialog" class="flex max-h-[85vh] w-full flex-col gap-4 rounded-2xl border border-border bg-surface p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-border pb-3">
                <div>
                    <h3 class="text-base font-semibold text-ink">Seluruh Arena Performa Staff</h3>
                    <p class="text-xs text-ink-muted">Tanda naik/turun dibanding periode sebelumnya dengan rentang tanggal yang sama.</p>
                </div>
                <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Tutup</button>
            </div>

            <div class="overflow-y-auto pr-1">
                <div class="space-y-2">
                    @foreach ($allLeaderboard as $row)
                        <div class="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-ink-muted">{{ $row['rank'] ?? $loop->iteration }}</span>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-100 text-sm font-bold text-brand-700">
                                @if (! empty($row['photo_path']))
                                    <img src="{{ $row['photo_path'] }}" alt="{{ $row['name'] }}" class="h-full w-full object-cover">
                                @else
                                    {{ strtoupper(mb_substr($row['name'] ?: 'U', 0, 1)) }}
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <strong class="block truncate text-sm font-medium text-ink">{{ $row['name'] }}</strong>
                                <span class="text-xs text-ink-muted">{{ $row['wilayah'] ?? '-' }} • {{ $row['unit_name'] ?? '-' }}</span>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <strong class="text-sm font-semibold text-ink">{{ number_format((float) $row['points'], 2, ',', '.') }} skor</strong>
                                {!! $movementPill($row['rank_delta'] ?? null, 'xs') !!}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </form>
    </dialog>
</section>
