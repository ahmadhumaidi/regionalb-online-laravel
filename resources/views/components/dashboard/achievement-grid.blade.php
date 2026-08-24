@props(['campusClosing', 'topStaffAchievement', 'topStaffMaxValue', 'gamification' => []])

@php
    $arenaByName = collect($gamification['all_leaderboard'] ?? [])
        ->keyBy(fn (array $row) => mb_strtolower(trim((string) ($row['name'] ?? ''))));
    $movementPill = function (?int $delta, string $size = 'xs'): string {
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

<section class="mb-6 grid gap-4 lg:grid-cols-2">
    <article class="rounded-2xl glass-card p-5">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-ink">Top 5 Pencapaian Kampus</h2>
                <span class="text-xs text-ink-muted">Kampus tertinggi, acuan: Closing Kampus Regional</span>
            </div>
            <a href="{{ route('closing-kampus') }}" class="shrink-0 text-xs font-semibold text-brand-600 underline">Lihat semua</a>
        </div>
        @php $topCampus = array_slice($campusClosing['rows'], 0, 5); @endphp
        @if (empty($topCampus))
            <p class="py-6 text-center text-sm text-ink-muted">Belum ada closing kampus pada periode/filter ini.</p>
        @else
            <div class="space-y-3">
                @foreach ($topCampus as $i => $row)
                    @php $rate = $campusClosing['max_value'] > 0 ? min(100, max(3, round($row['registrasi'] / $campusClosing['max_value'] * 100))) : 0; @endphp
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ $i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between text-sm">
                                <strong class="truncate font-medium text-ink">{{ $row['unit'] }}</strong>
                                <b class="shrink-0 font-semibold text-ink">{{ number_format($row['registrasi'], 0, ',', '.') }}</b>
                            </div>
                            <span class="text-xs text-ink-muted">{{ $row['regional'] }}</span>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-brand-600 progress-fill" style="width: {{ $rate }}%"></div></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </article>

    <article class="rounded-2xl glass-card p-5">
        <div class="mb-3 flex items-start justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-ink">Top 5 Pencapaian Staff Regional</h2>
                <span class="text-xs text-ink-muted">Acuan: Closing Personal Per Regional</span>
            </div>
            <a href="{{ route('pencapaian') }}" class="shrink-0 text-xs font-semibold text-brand-600 underline">Lihat selengkapnya</a>
        </div>
        @if (empty($topStaffAchievement))
            <p class="py-6 text-center text-sm text-ink-muted">Belum ada data pencapaian staff pada periode/filter ini.</p>
        @else
            <div class="space-y-3">
                @foreach ($topStaffAchievement as $i => $row)
                    @php
                        $rate = $topStaffMaxValue > 0 ? min(100, max(3, round($row['registrasi'] / $topStaffMaxValue * 100))) : 0;
                        $arenaRow = $arenaByName->get(mb_strtolower(trim((string) ($row['name'] ?? ''))), []);
                        $displayRank = $arenaRow['rank'] ?? ($i + 1);
                        $displayScore = (float) ($arenaRow['points'] ?? $row['registrasi']);
                        $displayLeague = $arenaRow['league'] ?? 'Starter';
                        $displayBadges = $arenaRow['badges'] ?? [];
                        $displayProgress = $arenaRow['rank_delta'] ?? null;
                        $displayCampus = $arenaRow['unit_name'] ?? $row['campus_name'] ?? '-';
                        $profileDialogId = 'top-staff-profile-'.($row['user_id'] ?? md5((string) ($row['name'] ?? 'staff')));
                        $photoDialogId = 'top-staff-photo-'.($row['user_id'] ?? md5((string) ($row['name'] ?? 'staff')));
                    @endphp
                    <button type="button" onclick="document.getElementById('{{ $profileDialogId }}').showModal()" class="flex w-full items-center gap-3 rounded-xl px-2 py-1.5 text-left transition duration-200 hover:bg-white/45 hover:shadow-sm">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ $i + 1 }}</span>
                        <span class="relative flex h-12 w-12 shrink-0 items-center justify-center transition duration-200 hover:scale-110">
                            <span class="absolute overflow-hidden rounded-full bg-brand-600 text-xs font-semibold text-white" style="left:50%;top:48%;width:52%;height:52%;transform:translate(-50%,-50%)">
                                @if (! empty($row['photo_path']))
                                    <img src="{{ $row['photo_path'] }}" class="h-full w-full object-cover" alt="{{ $row['name'] }}">
                                @else
                                    <span class="flex h-full w-full items-center justify-center">{{ strtoupper(mb_substr($row['name'], 0, 1)) }}</span>
                                @endif
                            </span>
                            <img src="{{ asset('images/league/'.strtolower($displayLeague).'.png') }}" alt="League {{ $displayLeague }}" class="pointer-events-none absolute inset-0 h-full w-full select-none" loading="lazy">
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between text-sm">
                                <strong class="truncate font-medium text-ink">{{ $row['name'] }}</strong>
                                <b class="shrink-0 font-semibold text-ink">{{ number_format($row['registrasi'], 0, ',', '.') }}</b>
                            </div>
                            <span class="text-xs text-ink-muted">{{ $row['regional'] }}</span>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-brand-600 progress-fill" style="width: {{ $rate }}%"></div></div>
                        </div>
                    </button>
                    <dialog id="{{ $profileDialogId }}" class="schedule-dialog" onclick="if (event.target === this) this.close()">
                        <form method="dialog" class="w-full max-w-md rounded-2xl border border-border bg-surface p-5 text-left shadow-2xl">
                            <div class="flex items-start justify-between gap-4 border-b border-border pb-4">
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        @if (! empty($row['photo_path']))
                                            onclick="event.stopPropagation(); document.getElementById('{{ $photoDialogId }}').showModal()"
                                        @endif
                                        class="relative flex h-20 w-20 shrink-0 items-center justify-center transition duration-200 hover:scale-110 {{ ! empty($row['photo_path']) ? 'cursor-zoom-in' : 'cursor-default' }}"
                                        aria-label="Lihat foto {{ $row['name'] }}"
                                        title="{{ ! empty($row['photo_path']) ? 'Lihat foto' : 'Foto belum tersedia' }}"
                                    >
                                        <span class="absolute overflow-hidden rounded-full bg-brand-600 text-lg font-semibold text-white" style="left:50%;top:48%;width:52%;height:52%;transform:translate(-50%,-50%)">
                                            @if (! empty($row['photo_path']))
                                                <img src="{{ $row['photo_path'] }}" class="h-full w-full object-cover" alt="{{ $row['name'] }}">
                                            @else
                                                <span class="flex h-full w-full items-center justify-center">{{ strtoupper(mb_substr($row['name'], 0, 1)) }}</span>
                                            @endif
                                        </span>
                                        <img src="{{ asset('images/league/'.strtolower($displayLeague).'.png') }}" alt="League {{ $displayLeague }}" class="pointer-events-none absolute inset-0 h-full w-full select-none" loading="lazy">
                                    </button>
                                    <div>
                                        <h3 class="text-base font-semibold text-ink">{{ $row['name'] }}</h3>
                                        <p class="mt-0.5 text-xs text-ink-muted">{{ $row['regional'] ?? '-' }} • {{ $displayCampus }}</p>
                                        <span class="mt-2 inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">League {{ $displayLeague }}</span>
                                    </div>
                                </div>
                                <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Tutup</button>
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2">
                                <div class="rounded-xl bg-surface-muted/70 p-3">
                                    <span class="text-[11px] font-medium text-ink-muted">Rank</span>
                                    <strong class="mt-1 block text-lg text-ink">#{{ $displayRank }}</strong>
                                </div>
                                <div class="rounded-xl bg-surface-muted/70 p-3">
                                    <span class="text-[11px] font-medium text-ink-muted">Skor</span>
                                    <strong class="mt-1 block text-lg text-ink">{{ number_format($displayScore, 2, ',', '.') }}</strong>
                                </div>
                                <div class="rounded-xl bg-surface-muted/70 p-3">
                                    <span class="text-[11px] font-medium text-ink-muted">Progress</span>
                                    <span class="mt-1 block">{!! $movementPill($displayProgress, 'xs') !!}</span>
                                </div>
                            </div>

                            <div class="mt-4">
                                <p class="mb-2 text-xs font-semibold text-ink-muted">Badge</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($displayBadges as $badge)
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">{{ $badge }}</span>
                                    @empty
                                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">On Progress</span>
                                    @endforelse
                                </div>
                            </div>
                        </form>
                    </dialog>
                    @if (! empty($row['photo_path']))
                        <dialog id="{{ $photoDialogId }}" class="schedule-dialog" onclick="if (event.target === this) this.close()">
                            <form method="dialog" class="w-full max-w-lg rounded-2xl border border-border bg-surface p-4 shadow-2xl">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-ink">{{ $row['name'] }}</h3>
                                        <p class="text-xs text-ink-muted">Foto profil</p>
                                    </div>
                                    <button type="submit" class="rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Tutup</button>
                                </div>
                                <div class="flex max-h-[75vh] items-center justify-center overflow-hidden rounded-xl bg-surface-muted">
                                    <img src="{{ $row['photo_path'] }}" alt="Foto profil {{ $row['name'] }}" class="max-h-[75vh] w-auto object-contain">
                                </div>
                            </form>
                        </dialog>
                    @endif
                @endforeach
            </div>
        @endif
    </article>
</section>
