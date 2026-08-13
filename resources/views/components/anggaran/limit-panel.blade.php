@props(['limits', 'canManageBudget' => false, 'period' => '', 'referenceOptions' => ['regionals' => []]])

<section class="mb-6 rounded-2xl glass-card p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-ink">Plafon Anggaran Kampus</h2>
        <span class="text-xs text-ink-muted">Batas anggaran iklan per kampus/unit untuk periode ini</span>
    </div>

    @if ($canManageBudget)
        <form method="POST" action="{{ route('anggaran.limit.store') }}" class="mb-4 grid gap-2 rounded-xl border border-border p-3 sm:grid-cols-5">
            @csrf
            <label class="grid gap-1 text-xs text-ink-muted">Periode anggaran<input name="ad_period" value="{{ old('ad_period', $period) }}" placeholder="Juli 2026" required class="rounded-lg border-border bg-surface-muted text-sm text-ink"></label>
            <label class="grid gap-1 text-xs text-ink-muted">Regional<select name="wilayah" required class="rounded-lg border-border bg-surface-muted text-sm text-ink"><option value="">Pilih regional</option>@foreach ($referenceOptions['regionals'] as $regionalOption)<option value="{{ $regionalOption }}" @selected(old('wilayah') === $regionalOption)>{{ $regionalOption }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Kampus/Unit<select name="unit_name" required class="rounded-lg border-border bg-surface-muted text-sm text-ink"><option value="">Pilih kampus</option>@foreach ($referenceOptions['campuses'] as $campusOption)<option value="{{ $campusOption['label'] }}" @selected(old('unit_name') === $campusOption['label'])>{{ $campusOption['label'] }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Besaran anggaran<input name="budget_limit" type="number" min="0.01" step="0.01" inputmode="numeric" placeholder="Contoh: 5000000" required class="rounded-lg border-border bg-surface-muted text-sm text-ink"></label>
            <label class="grid gap-1 text-xs text-ink-muted">Catatan (opsional)<input name="notes" value="{{ old('notes') }}" class="rounded-lg border-border bg-surface-muted text-sm text-ink"></label>
            <div class="sm:col-span-5"><button class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Simpan Plafon</button></div>
        </form>
    @endif

    @if (empty($limits))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada plafon kampus untuk periode ini.</p>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($limits as $row)
                @php
                    $rate = $row['budget_limit'] > 0 ? min(100, max(0, round($row['requested'] / $row['budget_limit'] * 100))) : 0;
                    $over = $row['remaining'] < 0;
                @endphp
                <article class="rounded-xl border border-border p-4">
                    <strong class="block text-sm font-semibold text-ink">{{ $row['unit_name'] ?: $row['wilayah'] }}</strong>
                    <span class="text-xs text-ink-muted">{{ $row['wilayah'] }}</span>

                    @if ($row['budget_limit'] > 0)
                        <p class="mt-1 text-xs text-ink-muted">Plafon: Rp {{ number_format($row['budget_limit'], 0, ',', '.') }}</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                            <div class="h-full rounded-full progress-fill {{ $over ? 'bg-tone-red' : 'bg-brand-600' }}" style="width: {{ $rate }}%"></div>
                        </div>
                        <p class="mt-2 text-xs font-medium {{ $over ? 'text-tone-red' : 'text-ink-muted' }}">
                            Sisa: Rp {{ number_format($row['remaining'], 0, ',', '.') }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-ink-muted">Plafon belum ditetapkan</p>
                    @endif

                    <p class="mt-2 text-xs text-ink-muted">{{ $row['count'] }} laporan &middot; Realisasi Rp {{ number_format($row['realization'], 0, ',', '.') }}</p>
                </article>
            @endforeach
        </div>
    @endif
</section>
