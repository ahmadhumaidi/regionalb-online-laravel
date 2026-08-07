@props(['pending'])

@if ($pending)
    <section class="mb-6 rounded-2xl border border-border bg-surface p-5">
        <h2 class="text-base font-semibold text-ink">Anggaran Perlu Dilaporkan</h2>
        <p class="mt-1 text-sm text-ink-muted">Otomatis muncul untuk anggaran kampus Anda yang belum atau belum tuntas dilaporkan</p>

        @if (! empty($pending['belum_dilaporkan']))
            <h3 class="mb-2 mt-4 text-sm font-semibold text-ink">Belum Dilaporkan ({{ count($pending['belum_dilaporkan']) }})</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs text-ink-muted">
                            <th class="py-2 pr-3 font-medium">Tanggal</th>
                            <th class="py-2 pr-3 font-medium">Platform/Campaign</th>
                            <th class="py-2 pr-3 text-right font-medium">Anggaran Disetujui</th>
                            <th class="py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending['belum_dilaporkan'] as $row)
                            <tr class="border-b border-border/60 last:border-0">
                                <td class="py-2 pr-3 text-ink-muted">{{ $row['report_date'] }}<br><span class="text-xs">{{ $row['ad_period'] }}</span></td>
                                <td class="py-2 pr-3 text-ink">{{ $row['platform'] ?: '-' }}<br><span class="text-xs text-ink-muted">{{ $row['campaign_name'] ?: '-' }}</span></td>
                                <td class="py-2 pr-3 text-right text-ink">Rp {{ number_format($row['budget_approved'], 0, ',', '.') }}</td>
                                <td class="py-2"><span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row['status'] ?: '-' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (! empty($pending['belum_tuntas']))
            <h3 class="mb-2 mt-5 text-sm font-semibold text-ink">Belum Tuntas Dilaporkan ({{ count($pending['belum_tuntas']) }})</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs text-ink-muted">
                            <th class="py-2 pr-3 font-medium">Tanggal</th>
                            <th class="py-2 pr-3 font-medium">Platform/Campaign</th>
                            <th class="py-2 pr-3 text-right font-medium">Realisasi</th>
                            <th class="py-2 pr-3 font-medium">Bukti</th>
                            <th class="py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending['belum_tuntas'] as $row)
                            <tr class="border-b border-border/60 last:border-0">
                                <td class="py-2 pr-3 text-ink-muted">{{ $row['report_date'] }}</td>
                                <td class="py-2 pr-3 text-ink">{{ $row['platform'] ?: '-' }}<br><span class="text-xs text-ink-muted">{{ $row['campaign_name'] ?: '-' }}</span></td>
                                <td class="py-2 pr-3 text-right text-ink">Rp {{ number_format($row['realization_amount'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3">
                                    @if ($row['has_attachment'])
                                        <span class="rounded-full bg-tone-green/10 px-2 py-0.5 text-[11px] font-medium text-tone-green">Ada</span>
                                    @else
                                        <span class="rounded-full bg-tone-red/10 px-2 py-0.5 text-[11px] font-medium text-tone-red">Belum ada</span>
                                    @endif
                                </td>
                                <td class="py-2"><span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row['status'] ?: '-' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if (empty($pending['belum_dilaporkan']) && empty($pending['belum_tuntas']))
            <p class="py-4 text-center text-sm text-ink-muted">Tidak ada anggaran yang perlu dilaporkan.</p>
        @endif
    </section>
@endif
