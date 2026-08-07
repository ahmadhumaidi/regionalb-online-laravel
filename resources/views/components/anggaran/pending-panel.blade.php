@props(['pending'])

@if ($pending)
    <section class="mb-6 grid gap-4 lg:grid-cols-2">
        <article class="rounded-2xl border border-border bg-surface p-5">
            <h2 class="mb-3 text-base font-semibold text-ink">Belum Dilaporkan</h2>
            @if (empty($pending['belum_dilaporkan']))
                <p class="py-4 text-center text-sm text-ink-muted">Tidak ada laporan tertunda.</p>
            @else
                <div class="space-y-2">
                    @foreach ($pending['belum_dilaporkan'] as $row)
                        <div class="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm">
                            <div><strong class="font-medium text-ink">{{ $row['unit_name'] ?: '-' }}</strong> <span class="text-xs text-ink-muted">{{ $row['ad_period'] }}</span></div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row['status'] ?: '-' }}</span>
                                @if ($row['can_report_realization'])
                                    <a href="{{ route('anggaran.realisasi.form', $row['id']) }}" class="text-[11px] font-semibold text-brand-700 underline">Lapor</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>

        <article class="rounded-2xl border border-border bg-surface p-5">
            <h2 class="mb-3 text-base font-semibold text-ink">Belum Tuntas Dilaporkan</h2>
            @if (empty($pending['belum_tuntas']))
                <p class="py-4 text-center text-sm text-ink-muted">Tidak ada laporan yang perlu dilengkapi.</p>
            @else
                <div class="space-y-2">
                    @foreach ($pending['belum_tuntas'] as $row)
                        <div class="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm">
                            <div><strong class="font-medium text-ink">{{ $row['unit_name'] ?: '-' }}</strong> <span class="text-xs text-ink-muted">{{ $row['ad_period'] }}</span></div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row['status'] ?: '-' }}</span>
                                @if ($row['can_report_realization'])
                                    <a href="{{ route('anggaran.realisasi.form', $row['id']) }}" class="text-[11px] font-semibold text-brand-700 underline">Lapor</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    </section>
@endif
