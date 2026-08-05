@props(['limits'])

<section class="mb-6 rounded-2xl border border-border bg-surface p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-ink">Plafon Anggaran Regional</h2>
        <span class="text-xs text-ink-muted">Batas anggaran iklan per wilayah untuk periode ini</span>
    </div>

    @if (empty($limits))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data wilayah untuk periode ini.</p>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($limits as $row)
                @php
                    $rate = $row['budget_limit'] > 0 ? min(100, max(0, round($row['requested'] / $row['budget_limit'] * 100))) : 0;
                    $over = $row['remaining'] < 0;
                @endphp
                <article class="rounded-xl border border-border p-4">
                    <strong class="block text-sm font-semibold text-ink">{{ $row['wilayah'] }}</strong>

                    @if ($row['budget_limit'] > 0)
                        <p class="mt-1 text-xs text-ink-muted">Plafon: Rp {{ number_format($row['budget_limit'], 0, ',', '.') }}</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                            <div class="h-full rounded-full {{ $over ? 'bg-tone-red' : 'bg-brand-600' }}" style="width: {{ $rate }}%"></div>
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
