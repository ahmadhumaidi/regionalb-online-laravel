@props(['regionalSummary', 'sources'])

<section class="mb-6 rounded-2xl glass-card p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-ink">Pencapaian per Regional</h2>
        <span class="text-xs text-ink-muted">Registrasi: {{ $sources['registrasi']['label'] ?? 'Closing Collab' }} &middot; Herregistrasi: {{ $sources['herregistrasi']['label'] ?? 'Herreg Collab' }}</span>
    </div>

    @if (empty($regionalSummary))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data pencapaian pada periode/filter ini.</p>
    @else
        @php $tones = ['blue-dark', 'blue', 'blue-light', 'red']; @endphp
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($regionalSummary as $i => $row)
                @php
                    $regRate = $row['target_registrasi'] > 0 ? min(100, max(0, round($row['registrasi'] / $row['target_registrasi'] * 100))) : 0;
                    $tone = $tones[$i % count($tones)];
                @endphp
                <article class="rounded-xl border border-ink/15 border-l-4 bg-surface-muted/70 p-4 shadow-sm" style="border-left-color: var(--color-tone-{{ $tone }})">
                    <strong class="block text-sm font-semibold text-ink">{{ $row['regional'] }}</strong>
                    <p class="mt-2 text-xs text-ink-muted">Registrasi</p>
                    <p class="text-lg font-bold" style="color: var(--color-tone-{{ $tone }})">{{ number_format($row['registrasi'], 0, ',', '.') }}
                        @if ($row['target_registrasi'] > 0)
                            <span class="text-xs font-normal text-ink-muted">/ {{ number_format($row['target_registrasi'], 0, ',', '.') }}</span>
                        @endif
                    </p>
                    @if ($row['target_registrasi'] > 0)
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface">
                            <div class="h-full rounded-full" style="width: {{ max(4, $regRate) }}%; background-color: var(--color-tone-{{ $tone }})"></div>
                        </div>
                    @endif
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
