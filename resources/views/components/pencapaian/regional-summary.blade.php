@props(['regionalSummary', 'sources'])

<section class="mb-6 rounded-2xl glass-card p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-ink">Pencapaian per Regional</h2>
        <span class="text-xs text-ink-muted">Registrasi: {{ $sources['registrasi']['label'] ?? 'Closing Collab' }} &middot; Herregistrasi: {{ $sources['herregistrasi']['label'] ?? 'Herreg Collab' }}</span>
    </div>

    @if (empty($regionalSummary))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data pencapaian pada periode/filter ini.</p>
    @else
        @php
            $tones = ['green', 'blue', 'orange', 'purple'];
        @endphp
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($regionalSummary as $row)
                @php
                    $regRate = $row['target_registrasi'] > 0 ? min(100, max(0, round($row['registrasi'] / $row['target_registrasi'] * 100))) : 0;
                    $tone = $tones[$loop->index % 4];
                @endphp
                <article class="rounded-xl border border-border p-4 shadow-sm">
                    <strong class="block text-sm font-semibold text-ink">{{ $row['regional'] }}</strong>
                    <p class="mt-2 text-xs text-ink-muted">Registrasi</p>
                    <p class="text-lg font-bold text-ink">{{ number_format($row['registrasi'], 0, ',', '.') }}
                        @if ($row['target_registrasi'] > 0)
                            <span class="text-xs font-normal text-ink-muted">/ {{ number_format($row['target_registrasi'], 0, ',', '.') }}</span>
                        @endif
                    </p>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full" style="background: color-mix(in srgb, var(--color-tone-{{ $tone }}) 25%, transparent)">
                        <div class="h-full rounded-full" style="width: {{ $regRate }}%; background: var(--color-tone-{{ $tone }})"></div>
                    </div>
                    <p class="mt-3 text-xs text-ink-muted">Herregistrasi</p>
                    <p class="text-sm font-semibold text-ink">{{ number_format($row['herregistrasi'], 0, ',', '.') }}
                        @if ($row['target_herregistrasi'] > 0)
                            <span class="text-xs font-normal text-ink-muted">/ {{ number_format($row['target_herregistrasi'], 0, ',', '.') }}</span>
                        @endif
                    </p>
                </article>
            @endforeach
        </div>
    @endif
</section>
