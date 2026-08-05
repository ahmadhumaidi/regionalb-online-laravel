@props(['reports'])

<section class="rounded-2xl border border-border bg-surface p-5">
    <h2 class="mb-4 text-base font-semibold text-ink">Laporan Harian Staff Terbaru</h2>
    @if (empty($reports))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada laporan pada periode/filter ini.</p>
    @else
        <div class="space-y-3">
            @foreach ($reports as $report)
                <div class="rounded-xl border border-border p-3.5">
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 text-xs text-ink-muted">
                            <span>{{ $report['report_date'] }}</span>
                            <span>&middot;</span>
                            <span>{{ $report['staff_name'] ?: '-' }}</span>
                            <span>&middot;</span>
                            <span>{{ $report['unit_name'] ?: '-' }}</span>
                        </div>
                        @if ($report['status'])
                            <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $report['status'] }}</span>
                        @endif
                    </div>
                    <p class="text-sm font-medium text-ink">{{ $report['title'] ?: '-' }}</p>
                    @if ($report['result_text'])
                        <p class="mt-1 text-xs text-ink-muted">{{ \Illuminate\Support\Str::limit($report['result_text'], 140) }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</section>
