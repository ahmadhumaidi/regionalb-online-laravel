@props(['campusClosing', 'topStaffAchievement', 'topStaffMaxValue'])

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
                @foreach ($topCampus as $row)
                    @php $rate = $campusClosing['max_value'] > 0 ? min(100, max(3, round($row['registrasi'] / $campusClosing['max_value'] * 100))) : 0; @endphp
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <strong class="truncate font-medium text-ink">{{ $row['unit'] }}</strong>
                            <b class="shrink-0 font-semibold text-ink">{{ number_format($row['registrasi'], 0, ',', '.') }}</b>
                        </div>
                        <span class="text-xs text-ink-muted">{{ $row['regional'] }}</span>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-brand-600" style="width: {{ $rate }}%"></div></div>
                    </div>
                @endforeach
            </div>
        @endif
    </article>

    <article class="rounded-2xl glass-card p-5">
        <div class="mb-3">
            <h2 class="text-base font-semibold text-ink">Pencapaian Staff Regional</h2>
            <span class="text-xs text-ink-muted">Acuan: Closing Personal Per Regional</span>
        </div>
        @if (empty($topStaffAchievement))
            <p class="py-6 text-center text-sm text-ink-muted">Belum ada data pencapaian staff pada periode/filter ini.</p>
        @else
            <div class="space-y-3">
                @foreach ($topStaffAchievement as $i => $row)
                    @php $rate = $topStaffMaxValue > 0 ? min(100, max(3, round($row['registrasi'] / $topStaffMaxValue * 100))) : 0; @endphp
                    <div class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ $i + 1 }}</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-xs font-semibold text-white">
                            @if (! empty($row['photo_path']))
                                <img src="{{ $row['photo_path'] }}" class="h-full w-full object-cover" alt="{{ $row['name'] }}">
                            @else
                                {{ strtoupper(mb_substr($row['name'], 0, 1)) }}
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between text-sm">
                                <strong class="truncate font-medium text-ink">{{ $row['name'] }}</strong>
                                <b class="shrink-0 font-semibold text-ink">{{ number_format($row['registrasi'], 0, ',', '.') }}</b>
                            </div>
                            <span class="text-xs text-ink-muted">{{ $row['regional'] }}</span>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface-muted"><div class="h-full rounded-full bg-brand-600" style="width: {{ $rate }}%"></div></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </article>
</section>
