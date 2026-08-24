@props(['gamification'])

@php
    $leaderboard = $gamification['leaderboard'] ?? [];
    $allLeaderboard = $gamification['all_leaderboard'] ?? $leaderboard;
    $profileDialogId = fn (array $row) => 'arena-profile-dialog-'.($row['user_id'] ?? md5((string) ($row['name'] ?? 'staff')));
    $movementPill = function (?int $delta, string $size = 'sm'): string {
        $base = $size === 'xs'
            ? 'rounded-full px-2 py-0.5 text-[11px] font-semibold'
            : 'rounded-full px-2.5 py-1 text-xs font-semibold';

        if ($delta === null) {
            return '<span class="'.$base.' bg-white text-sky-700">Baru</span>';
        }
        if ($delta > 0) {
            return '<span class="'.$base.' bg-white text-emerald-700">&#9650; +'.$delta.'</span>';
        }
        if ($delta < 0) {
            return '<span class="'.$base.' bg-white text-rose-700">&#9660; -'.abs($delta).'</span>';
        }

        return '<span class="'.$base.' bg-white text-ink-muted">-</span>';
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
        <button type="button" onclick="document.getElementById('{{ $profileDialogId($gamification['my_rank']) }}').showModal()" class="mb-4 flex w-full flex-col gap-3 rounded-xl bg-brand-50 px-4 py-3 text-left shadow-sm shadow-slate-900/10 transition duration-200 hover:bg-brand-100 hover:shadow-md sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <span class="relative flex h-12 w-12 shrink-0 items-center justify-center transition duration-200 hover:scale-110">
                    <span class="absolute overflow-hidden rounded-full bg-brand-100 text-xs font-bold text-brand-700" style="left:50%;top:48%;width:52%;height:52%;transform:translate(-50%,-50%)">
                        @if (! empty($gamification['my_rank']['photo_path']))
                            <img src="{{ $gamification['my_rank']['photo_path'] }}" alt="{{ $gamification['my_rank']['name'] }}" class="h-full w-full object-cover">
                        @else
                            <span class="flex h-full w-full items-center justify-center">{{ strtoupper(mb_substr($gamification['my_rank']['name'] ?: 'U', 0, 1)) }}</span>
                        @endif
                    </span>
                    <img src="{{ asset('images/league/'.strtolower($gamification['my_rank']['league'] ?? 'Starter').'.png') }}" alt="League {{ $gamification['my_rank']['league'] ?? 'Starter' }}" class="pointer-events-none absolute inset-0 h-full w-full select-none" loading="lazy">
                </span>
                <strong class="text-sm font-semibold text-brand-700">{{ $gamification['my_rank']['name'] }}</strong>
                <span class="text-xs text-brand-700/70">Progress</span>
                {!! $movementPill($gamification['my_rank']['rank_delta'] ?? null, 'xs') !!}
            </div>
            <div class="flex items-center gap-3 sm:justify-end">
                <span class="text-xs font-semibold text-brand-700/70">Rank #{{ $gamification['my_rank']['rank'] ?? '-' }}</span>
                <strong class="text-lg font-bold text-brand-700">{{ number_format((float) $gamification['my_rank']['points'], 2, ',', '.') }} skor</strong>
            </div>
        </button>
    @endif

    @if (empty($leaderboard))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data performa staff pada periode/filter ini.</p>
    @else
        <div class="space-y-2">
            @foreach ($leaderboard as $row)
                <button type="button" onclick="document.getElementById('{{ $profileDialogId($row) }}').showModal()" class="flex w-full items-center gap-3 rounded-xl border border-border px-3 py-2.5 text-left shadow-sm shadow-slate-900/10 transition duration-200 hover:border-brand-200 hover:bg-white/45 hover:shadow-md">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-ink-muted">{{ $row['rank'] ?? $loop->iteration }}</span>
                    <span class="relative flex h-12 w-12 shrink-0 items-center justify-center transition duration-200 hover:scale-110">
                        <span class="absolute overflow-hidden rounded-full bg-brand-100 text-xs font-bold text-brand-700" style="left:50%;top:48%;width:52%;height:52%;transform:translate(-50%,-50%)">
                            @if (! empty($row['photo_path']))
                                <img src="{{ $row['photo_path'] }}" alt="{{ $row['name'] }}" class="h-full w-full object-cover">
                            @else
                                <span class="flex h-full w-full items-center justify-center">{{ strtoupper(mb_substr($row['name'] ?: 'U', 0, 1)) }}</span>
                            @endif
                        </span>
                        <img src="{{ asset('images/league/'.strtolower($row['league'] ?? 'Starter').'.png') }}" alt="League {{ $row['league'] ?? 'Starter' }}" class="pointer-events-none absolute inset-0 h-full w-full select-none" loading="lazy">
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
                </button>
            @endforeach
        </div>
    @endif

    <dialog id="arena-leaderboard-dialog" class="schedule-dialog arena-leaderboard-dialog" onclick="if (event.target === this) this.close()">
        <form method="dialog" class="flex max-h-[85vh] w-full flex-col gap-4 rounded-2xl border border-border bg-surface p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-border pb-3">
                <div>
                    <h3 class="text-base font-semibold text-ink">Seluruh Arena Performa Staff</h3>
                    <p class="text-xs text-ink-muted">Tanda naik/turun dihitung harian dari posisi leaderboard kemarin.</p>
                </div>
                <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Tutup</button>
            </div>

            <div class="overflow-y-auto pr-1">
                <div class="space-y-2">
                    @foreach ($allLeaderboard as $row)
                        <button type="button" onclick="document.getElementById('{{ $profileDialogId($row) }}').showModal()" class="flex w-full items-center gap-3 rounded-xl border border-border px-3 py-2.5 text-left shadow-sm shadow-slate-900/10 transition duration-200 hover:border-brand-200 hover:bg-surface-muted hover:shadow-md">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted text-xs font-semibold text-ink-muted">{{ $row['rank'] ?? $loop->iteration }}</span>
                            <span class="relative flex h-12 w-12 shrink-0 items-center justify-center transition duration-200 hover:scale-110">
                                <span class="absolute overflow-hidden rounded-full bg-brand-100 text-xs font-bold text-brand-700" style="left:50%;top:48%;width:52%;height:52%;transform:translate(-50%,-50%)">
                                    @if (! empty($row['photo_path']))
                                        <img src="{{ $row['photo_path'] }}" alt="{{ $row['name'] }}" class="h-full w-full object-cover">
                                    @else
                                        <span class="flex h-full w-full items-center justify-center">{{ strtoupper(mb_substr($row['name'] ?: 'U', 0, 1)) }}</span>
                                    @endif
                                </span>
                                <img src="{{ asset('images/league/'.strtolower($row['league'] ?? 'Starter').'.png') }}" alt="League {{ $row['league'] ?? 'Starter' }}" class="pointer-events-none absolute inset-0 h-full w-full select-none" loading="lazy">
                            </span>
                            <div class="min-w-0 flex-1">
                                <strong class="block truncate text-sm font-medium text-ink">{{ $row['name'] }}</strong>
                                <span class="text-xs text-ink-muted">{{ $row['wilayah'] ?? '-' }} • {{ $row['unit_name'] ?? '-' }}</span>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <strong class="text-sm font-semibold text-ink">{{ number_format((float) $row['points'], 2, ',', '.') }} skor</strong>
                                {!! $movementPill($row['rank_delta'] ?? null, 'xs') !!}
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        </form>
    </dialog>

    @foreach ($allLeaderboard as $row)
        @php
            $fullPhotoUrl = $row['photo_path'] ?? null;
            $fullPhotoDialogId = $profileDialogId($row).'-photo';
        @endphp
        <dialog id="{{ $profileDialogId($row) }}" class="schedule-dialog" onclick="if (event.target === this) this.close()">
            <form method="dialog" class="w-full max-w-md rounded-2xl border border-border bg-surface p-5 text-left shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-border pb-4">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @if ($fullPhotoUrl)
                                onclick="event.stopPropagation(); document.getElementById('{{ $fullPhotoDialogId }}').showModal()"
                            @endif
                            class="relative flex h-20 w-20 shrink-0 items-center justify-center transition duration-200 hover:scale-110 {{ $fullPhotoUrl ? 'cursor-zoom-in' : 'cursor-default' }}"
                            aria-label="Lihat foto {{ $row['name'] }}"
                            title="{{ $fullPhotoUrl ? 'Lihat foto' : 'Foto belum tersedia' }}"
                        >
                            <span class="absolute overflow-hidden rounded-full bg-brand-100 text-lg font-bold text-brand-700" style="left:50%;top:48%;width:52%;height:52%;transform:translate(-50%,-50%)">
                                @if (! empty($row['photo_path']))
                                    <img src="{{ $row['photo_path'] }}" alt="{{ $row['name'] }}" class="h-full w-full object-cover">
                                @else
                                    <span class="flex h-full w-full items-center justify-center">{{ strtoupper(mb_substr($row['name'] ?: 'U', 0, 1)) }}</span>
                                @endif
                            </span>
                            <img src="{{ asset('images/league/'.strtolower($row['league'] ?? 'Starter').'.png') }}" alt="League {{ $row['league'] ?? 'Starter' }}" class="pointer-events-none absolute inset-0 h-full w-full select-none" loading="lazy">
                        </button>
                        <div>
                            <h3 class="text-base font-semibold text-ink">{{ $row['name'] }}</h3>
                            <p class="mt-0.5 text-xs text-ink-muted">{{ $row['wilayah'] ?? '-' }} • {{ $row['unit_name'] ?? '-' }}</p>
                            <span class="mt-2 inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">League {{ $row['league'] ?? 'Starter' }}</span>
                        </div>
                    </div>
                    <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Tutup</button>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    <div class="rounded-xl bg-surface-muted/70 p-3">
                        <span class="text-[11px] font-medium text-ink-muted">Rank</span>
                        <strong class="mt-1 block text-lg text-ink">#{{ $row['rank'] ?? '-' }}</strong>
                    </div>
                    <div class="rounded-xl bg-surface-muted/70 p-3">
                        <span class="text-[11px] font-medium text-ink-muted">Skor</span>
                        <strong class="mt-1 block text-lg text-ink">{{ number_format((float) $row['points'], 2, ',', '.') }}</strong>
                    </div>
                    <div class="rounded-xl bg-surface-muted/70 p-3">
                        <span class="text-[11px] font-medium text-ink-muted">Progress</span>
                        <span class="mt-1 block">{!! $movementPill($row['rank_delta'] ?? null, 'xs') !!}</span>
                    </div>
                </div>

                <div class="mt-4">
                    <p class="mb-2 text-xs font-semibold text-ink-muted">Badge</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($row['badges'] as $badge)
                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">{{ $badge }}</span>
                        @endforeach
                    </div>
                </div>
            </form>
        </dialog>
        @if ($fullPhotoUrl)
            <dialog id="{{ $fullPhotoDialogId }}" class="schedule-dialog" onclick="if (event.target === this) this.close()">
                <form method="dialog" class="w-full max-w-lg rounded-2xl border border-border bg-surface p-4 shadow-2xl">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-ink">{{ $row['name'] }}</h3>
                            <p class="text-xs text-ink-muted">Foto profil</p>
                        </div>
                        <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Tutup</button>
                    </div>
                    <div class="flex max-h-[75vh] items-center justify-center overflow-hidden rounded-xl bg-surface-muted">
                        <img src="{{ $fullPhotoUrl }}" alt="Foto profil {{ $row['name'] }}" class="max-h-[75vh] w-auto object-contain">
                    </div>
                </form>
            </dialog>
        @endif
    @endforeach
</section>
