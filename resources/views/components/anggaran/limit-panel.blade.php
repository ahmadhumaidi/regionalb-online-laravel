@props(['limits', 'canManageBudget' => false, 'period' => '', 'referenceOptions' => ['regionals' => []]])

<section class="mb-6 rounded-2xl glass-card p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-ink">Plafon Anggaran</h2>
        <span class="text-xs text-ink-muted">Super User menetapkan plafon regional, lalu Korwil membaginya per kampus/unit</span>
    </div>

    @if ($canManageBudget)
        <form method="POST" action="{{ route('anggaran.limit.store') }}" class="mb-4 grid gap-2 rounded-xl border border-border p-3 sm:grid-cols-5">
            @csrf
            <label class="grid gap-1 text-xs text-ink-muted">Periode anggaran<input name="ad_period" value="{{ old('ad_period', $period) }}" placeholder="Juli 2026" required class="rounded-lg border-border bg-surface-muted text-sm text-ink"></label>
            @if (auth()->user()->role === 'koordinator')
                <input type="hidden" name="wilayah" value="{{ auth()->user()->regional }}">
                <label class="grid gap-1 text-xs text-ink-muted">Regional<input value="{{ auth()->user()->regional }}" disabled class="rounded-lg border-border bg-surface-muted text-sm text-ink"></label>
                <label class="grid gap-1 text-xs text-ink-muted">Kampus/Unit<select name="unit_name" required class="rounded-lg border-border bg-surface-muted text-sm text-ink"><option value="">Pilih kampus/unit</option>@foreach ($referenceOptions['campuses'] as $campusOption)<option value="{{ $campusOption['label'] }}" @selected(old('unit_name') === $campusOption['label'])>{{ $campusOption['label'] }}</option>@endforeach</select></label>
            @else
                <label class="grid gap-1 text-xs text-ink-muted sm:col-span-2">Regional<select name="wilayah" required class="rounded-lg border-border bg-surface-muted text-sm text-ink"><option value="">Pilih regional</option>@foreach ($referenceOptions['regionals'] as $regionalOption)<option value="{{ $regionalOption }}" @selected(old('wilayah') === $regionalOption)>{{ $regionalOption }}</option>@endforeach</select></label>
            @endif
            <label class="grid gap-1 text-xs text-ink-muted">Besaran anggaran<input name="budget_limit" type="number" min="0.01" step="0.01" inputmode="numeric" placeholder="Contoh: 5000000" required class="rounded-lg border-border bg-surface-muted text-sm text-ink"></label>
            <label class="grid gap-1 text-xs text-ink-muted">Catatan (opsional)<input name="notes" value="{{ old('notes') }}" class="rounded-lg border-border bg-surface-muted text-sm text-ink"></label>
            <div class="sm:col-span-5"><button class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Simpan Plafon</button></div>
        </form>
    @endif

    @if (empty($limits))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada plafon untuk periode ini.</p>
    @elseif (auth()->user()->role === 'staff')
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($limits as $row)
                @php $unitRate = $row['budget_limit'] > 0 ? min(100, max(0, round($row['requested'] / $row['budget_limit'] * 100))) : 0; @endphp
                <article class="rounded-xl border border-border bg-surface/60 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Anggaran iklan unit</p>
                    <div class="mt-1 flex items-start justify-between gap-3">
                        <strong class="text-sm text-ink">{{ $row['unit_name'] }}</strong>
                        <strong class="shrink-0 text-sm text-ink">Rp {{ number_format($row['budget_limit'], 0, ',', '.') }}</strong>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-muted">
                        <div class="h-full rounded-full bg-brand-600 progress-fill" style="width: {{ $unitRate }}%"></div>
                    </div>
                    <div class="mt-2 flex justify-between gap-2 text-xs text-ink-muted">
                        <span>Dicairkan Rp {{ number_format($row['approved'], 0, ',', '.') }}</span>
                        <span>Realisasi Rp {{ number_format($row['realization'], 0, ',', '.') }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        @php $limitGroups = collect($limits)->groupBy('wilayah'); @endphp
        <div class="grid items-start gap-4 lg:grid-cols-2">
            @foreach ($limitGroups as $wilayah => $regionalRows)
                @php
                    $regionalLimit = $regionalRows->firstWhere('unit_name', '');
                    $unitRows = $regionalRows->filter(fn ($row) => $row['unit_name'] !== '')->values();
                    $pool = (float) ($regionalLimit['budget_limit'] ?? 0);
                    $allocated = (float) $unitRows->sum('budget_limit');
                    $unallocated = $pool - $allocated;
                    $requested = (float) ($regionalLimit['requested'] ?? $unitRows->sum('requested'));
                    $realization = (float) ($regionalLimit['realization'] ?? $unitRows->sum('realization'));
                    $rate = $pool > 0 ? min(100, max(0, round($requested / $pool * 100))) : 0;
                @endphp
                <article class="overflow-hidden rounded-2xl border border-border bg-surface/60">
                    <header class="border-b border-border bg-brand-600/10 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-brand-600">Plafon regional</p>
                                <h3 class="mt-0.5 text-lg font-semibold text-ink">{{ $wilayah }}</h3>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-ink-muted">Total plafon</p>
                                <p class="text-lg font-bold text-ink">Rp {{ number_format($pool, 0, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2 text-xs">
                            <div class="rounded-lg bg-surface/80 p-2">
                                <span class="block text-ink-muted">Dialokasikan</span>
                                <strong class="mt-0.5 block text-ink">Rp {{ number_format($allocated, 0, ',', '.') }}</strong>
                            </div>
                            <div class="rounded-lg bg-surface/80 p-2">
                                <span class="block text-ink-muted">Belum dialokasikan</span>
                                <strong class="mt-0.5 block {{ $unallocated < 0 ? 'text-tone-red' : 'text-tone-green' }}">Rp {{ number_format($unallocated, 0, ',', '.') }}</strong>
                            </div>
                            <div class="rounded-lg bg-surface/80 p-2">
                                <span class="block text-ink-muted">Realisasi</span>
                                <strong class="mt-0.5 block text-ink">Rp {{ number_format($realization, 0, ',', '.') }}</strong>
                            </div>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-muted">
                            <div class="h-full rounded-full bg-brand-600 progress-fill" style="width: {{ $rate }}%"></div>
                        </div>
                        <p class="mt-1.5 text-right text-xs text-ink-muted">Pengajuan Rp {{ number_format($requested, 0, ',', '.') }} ({{ $rate }}% dari plafon)</p>
                    </header>

                    <div class="p-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-ink">Alokasi kampus/unit</h4>
                            <span class="rounded-full bg-surface-muted px-2 py-1 text-xs text-ink-muted">{{ $unitRows->count() }} unit</span>
                        </div>
                        @if ($unitRows->isEmpty())
                            <p class="rounded-lg border border-dashed border-border px-3 py-5 text-center text-xs text-ink-muted">Belum dibagi ke kampus atau unit.</p>
                        @else
                            <div class="divide-y divide-border overflow-hidden rounded-xl border border-border">
                                @foreach ($unitRows as $row)
                                    @php
                                        $unitRate = $row['budget_limit'] > 0 ? min(100, max(0, round($row['requested'] / $row['budget_limit'] * 100))) : 0;
                                        $isRegionalAd = str_starts_with($row['unit_name'], 'Iklan Regional ');
                                    @endphp
                                    <div class="p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <strong class="block truncate text-sm text-ink">{{ $row['unit_name'] }}</strong>
                                                <span class="text-xs text-ink-muted">{{ $isRegionalAd ? 'Iklan wilayah · dikelola Korwil' : 'Alokasi kampus' }} · {{ $row['count'] }} laporan</span>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-2">
                                                <strong class="text-sm text-ink">Rp {{ number_format($row['budget_limit'], 0, ',', '.') }}</strong>
                                                @if (auth()->user()->role === 'super_user')
                                                    <form method="POST" action="{{ route('anggaran.limit.destroy') }}" onsubmit="return confirm('Hapus plafon {{ $row['unit_name'] }} periode {{ $period }}? Laporan iklan tetap tersimpan.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="ad_period" value="{{ $period }}">
                                                        <input type="hidden" name="wilayah" value="{{ $wilayah }}">
                                                        <input type="hidden" name="unit_name" value="{{ $row['unit_name'] }}">
                                                        <button type="submit" title="Hapus plafon {{ $row['unit_name'] }}" aria-label="Hapus plafon {{ $row['unit_name'] }}" class="rounded-md border border-tone-red/30 p-1.5 text-tone-red hover:bg-tone-red/10"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                                            <div class="h-full rounded-full {{ $row['remaining'] < 0 ? 'bg-tone-red' : 'bg-brand-600' }} progress-fill" style="width: {{ $unitRate }}%"></div>
                                        </div>
                                        <div class="mt-1.5 flex justify-between gap-2 text-xs text-ink-muted">
                                            <span>Pengajuan Rp {{ number_format($row['requested'], 0, ',', '.') }}</span>
                                            <span class="{{ $row['remaining'] < 0 ? 'font-semibold text-tone-red' : '' }}">Sisa Rp {{ number_format($row['remaining'], 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
