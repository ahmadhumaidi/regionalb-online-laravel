@props(['regionalSummary', 'sources'])

<section class="mb-6 rounded-2xl glass-card p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-ink">Pencapaian per Regional</h2>
        <span class="text-xs text-ink-muted">Registrasi: {{ $sources['registrasi']['label'] ?? 'Closing Personal Per Regional' }} &middot; Herregistrasi: {{ $sources['herregistrasi']['label'] ?? 'Herreg Personal Per Regional' }}</span>
    </div>

    @if (empty($regionalSummary))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data pencapaian pada periode/filter ini.</p>
    @else
        @php
            $tones = ['blue-dark', 'blue', 'blue-light', 'red'];
            $maxRegistrasi = collect($regionalSummary)->max('registrasi') ?: 0;
        @endphp
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ($regionalSummary as $i => $row)
                @php
                    // Relative to the highest regional here, not the (often
                    // unset) target - always gives a real, visible bar
                    // instead of one that's blank whenever no target exists.
                    $regRate = $maxRegistrasi > 0 ? min(100, max(4, round($row['registrasi'] / $maxRegistrasi * 100))) : 4;
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
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-surface">
                        <div class="h-full rounded-full" style="width: {{ $regRate }}%; background-color: var(--color-tone-{{ $tone }})"></div>
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
