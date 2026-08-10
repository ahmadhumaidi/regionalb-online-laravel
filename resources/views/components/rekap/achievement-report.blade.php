@props(['report'])

@php
    $gradients = [
        'linear-gradient(135deg, var(--color-tone-green) 0%, #15803d 100%)',
        'linear-gradient(135deg, var(--color-tone-blue-light) 0%, var(--color-tone-blue) 100%)',
        'linear-gradient(135deg, #92400e 0%, var(--color-tone-orange) 100%)',
        'linear-gradient(135deg, var(--color-tone-purple) 0%, #a78bfa 100%)',
    ];
@endphp

<div class="@container">
    <h2 class="text-base font-semibold text-ink">Laporan Pencapaian</h2>
    <p class="mt-1 text-sm text-ink-muted">{{ $report['note'] }}</p>

    <div class="mt-4 rounded-xl border border-tone-green/30 bg-tone-green/5 p-4">
        <div class="flex items-center gap-3">
            <span class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-base font-semibold text-white">
                @if ($report['leader']['photo'])
                    <img src="{{ $report['leader']['photo'] }}" alt="{{ $report['leader']['name'] }}" class="h-full w-full object-cover">
                @else
                    {{ strtoupper(mb_substr($report['leader']['name'] ?: 'U', 0, 1)) }}
                @endif
            </span>
            <div class="leading-tight">
                <span class="block text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ $report['leader']['label'] }}</span>
                <span class="block text-base font-bold text-ink">{{ $report['leader']['name'] }}</span>
                <span class="block text-sm font-bold text-tone-green">{{ number_format($report['leader']['registrasi'], 0, ',', '.') }} closing staff</span>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 @3xl:grid-cols-2">
        @foreach ($report['regionals'] as $i => $regional)
            <div class="overflow-hidden rounded-xl border border-border">
                <div class="flex items-center justify-between gap-3 p-4" style="background: {{ $gradients[$i % 4] }}">
                    <div class="leading-tight">
                        <span class="block text-xs font-semibold tracking-wide text-white/80 uppercase">{{ $regional['regional'] }}</span>
                        <span class="block text-lg font-bold text-white">{{ number_format($regional['registrasi'], 0, ',', '.') }} closing staff</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white/25 text-xs font-semibold text-white ring-2 ring-white/40">
                            @if ($regional['korwil_photo'])
                                <img src="{{ $regional['korwil_photo'] }}" alt="{{ $regional['korwil_name'] }}" class="h-full w-full object-cover">
                            @else
                                {{ strtoupper(mb_substr($regional['korwil_name'] ?: 'K', 0, 1)) }}
                            @endif
                        </span>
                        <span class="leading-tight">
                            <span class="block text-[10px] font-semibold tracking-wide text-white/80 uppercase">Korwil</span>
                            <span class="block text-xs font-semibold text-white">{{ $regional['korwil_name'] }}</span>
                        </span>
                    </div>
                </div>
                <div class="bg-surface p-4">
                    @if (empty($regional['units']))
                        <p class="text-sm text-ink-muted">Belum ada unit closing pada periode ini.</p>
                    @else
                        <div class="space-y-2">
                            @foreach ($regional['units'] as $unit)
                                <div class="rounded-lg border border-border p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <strong class="text-sm text-ink">{{ $unit['unit'] }}</strong>
                                        <span class="text-xs text-ink-muted">{{ number_format($unit['registrasi'], 0, ',', '.') }} closing staff</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($unit['staff'] as $staff)
                                            <div class="flex items-center gap-1.5 rounded-full border border-border bg-surface-muted/60 py-1 pr-2 pl-1">
                                                <span class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-[10px] font-semibold text-white">
                                                    @if ($staff['photo'])
                                                        <img src="{{ $staff['photo'] }}" alt="{{ $staff['name'] }}" class="h-full w-full object-cover">
                                                    @else
                                                        {{ strtoupper(mb_substr($staff['name'] ?: 'S', 0, 1)) }}
                                                    @endif
                                                </span>
                                                <span class="text-xs text-ink">{{ $staff['name'] }} <span class="text-ink-muted">· {{ number_format($staff['registrasi'], 0, ',', '.') }}</span></span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
