@props(['groups'])

<section class="rounded-2xl glass-card p-5">
    <h2 class="mb-4 text-base font-semibold text-ink">Anggaran & Laporan Iklan</h2>
    @if (empty($groups))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada laporan iklan pada periode ini.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1080px] text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs text-ink-muted">
                        <th class="py-2 pr-3 font-medium">Kampus / Platform</th>
                        <th class="py-2 pr-3 font-medium">Tanggal</th>
                        <th class="py-2 pr-3 text-right font-medium">Anggaran</th>
                        <th class="py-2 pr-3 text-right font-medium">Realisasi</th>
                        <th class="py-2 pr-3 text-right font-medium">Leads</th>
                        <th class="py-2 pr-3 text-right font-medium">Closing</th>
                        <th class="py-2 pr-3 text-right font-medium">CPL</th>
                        <th class="py-2 pr-3 font-medium">Status</th>
                        <th class="py-2 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $regional)
                        <tr class="bg-brand-50/60">
                            <td colspan="2" class="py-2 pr-3 font-semibold text-brand-700">
                                Regional: {{ $regional['wilayah'] }} <span class="font-normal text-ink-muted">({{ $regional['subtotal']['count'] }} laporan)</span>
                            </td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">{{ number_format($regional['subtotal']['requested'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">{{ number_format($regional['subtotal']['realization'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">{{ number_format($regional['subtotal']['leads'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">{{ number_format($regional['subtotal']['closing'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">{{ number_format($regional['subtotal']['cpl'], 0, ',', '.') }}</td>
                            <td class="py-2"></td>
                            <td class="py-2"></td>
                        </tr>
                        @foreach ($regional['campuses'] as $campus)
                            <tr class="bg-surface-muted/70">
                                <td colspan="2" class="py-1.5 pr-3 pl-4 font-medium text-ink">
                                    {{ $campus['label'] }} <span class="font-normal text-ink-muted">({{ $campus['subtotal']['count'] }})</span>
                                </td>
                                <td class="py-1.5 pr-3 text-right text-ink">{{ number_format($campus['subtotal']['requested'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right text-ink">{{ number_format($campus['subtotal']['realization'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right text-ink">{{ number_format($campus['subtotal']['leads'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right text-ink">{{ number_format($campus['subtotal']['closing'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right text-ink">{{ number_format($campus['subtotal']['cpl'], 0, ',', '.') }}</td>
                                <td class="py-1.5"></td>
                                <td class="py-1.5"></td>
                            </tr>
                            @foreach ($campus['rows'] as $row)
                                <tr class="border-b border-border/60">
                                    <td class="py-2 pr-3 pl-8 text-ink">{{ $row['campaign_name'] ?: '-' }} <span class="text-xs text-ink-muted">{{ $row['platform'] }}</span></td>
                                    <td class="py-2 pr-3 text-ink-muted">{{ $row['report_date'] }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['budget_requested'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['realization_amount'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['leads_count'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['closing_count'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['cpl'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row['status'] ?: '-' }}</span>
                                        @if ($row['has_attachment'])
                                            <x-icon name="clipboard" class="ml-1 inline h-3.5 w-3.5 text-ink-muted" />
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <a href="{{ route('reports.show', $row['id']) }}" title="Lihat" aria-label="Lihat" class="inline-flex items-center gap-1 rounded-md border border-border px-2 py-1 text-[11px] font-semibold text-ink-muted hover:bg-surface-muted hover:text-ink"><x-icon name="eye" class="h-3.5 w-3.5" />Lihat</a>
                                            @if ($row['has_attachment'])
                                                <a href="{{ route('reports.attachment', $row['id']) }}" target="_blank" rel="noopener" title="Lihat invoice/bukti transfer" aria-label="Lihat invoice" class="inline-flex items-center gap-1 rounded-md border border-l-2 border-border border-l-tone-blue px-2 py-1 text-[11px] font-semibold text-tone-blue hover:bg-surface-muted"><x-icon name="document" class="h-3.5 w-3.5" />Invoice</a>
                                            @endif
                                            @if ($row['has_insight_attachment'])
                                                <a href="{{ route('reports.insight-attachment', $row['id']) }}" target="_blank" rel="noopener" title="Lihat bukti insight/pengeluaran iklan" aria-label="Lihat insight" class="inline-flex items-center gap-1 rounded-md border border-l-2 border-border border-l-tone-orange px-2 py-1 text-[11px] font-semibold text-tone-orange hover:bg-surface-muted"><x-icon name="bolt" class="h-3.5 w-3.5" />Insight</a>
                                            @endif
                                            @if ($row['has_ad_leads'])
                                                <a href="{{ route('reports.show', $row['id']) }}#data-hasil-iklan" title="Lihat data hasil iklan" aria-label="Lihat data hasil iklan" class="inline-flex items-center gap-1 rounded-md border border-l-2 border-border border-l-tone-green px-2 py-1 text-[11px] font-semibold text-tone-green hover:bg-surface-muted"><x-icon name="chart-bar" class="h-3.5 w-3.5" />Data</a>
                                            @endif
                                            @if ($row['can_edit'])
                                                <a href="{{ route('reports.edit', $row['id']) }}" title="Edit" aria-label="Edit" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="edit" class="h-3.5 w-3.5" /></a>
                                            @endif
                                            @if ($row['can_delete'])
                                                <form method="POST" action="{{ route('reports.destroy', $row['id']) }}" onsubmit="return confirm('Hapus laporan ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Hapus" aria-label="Hapus" class="rounded-md border border-tone-red p-1 text-tone-red"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                                </form>
                                            @endif
                                            @if ($row['can_review'])
                                                <form method="POST" action="{{ route('anggaran.setujui', $row['id']) }}" class="flex items-center gap-1" onsubmit="return confirm('Setujui pengajuan ini?')">
                                                    @csrf
                                                    <input type="number" name="budget_approved" value="{{ number_format($row['budget_requested'], 2, '.', '') }}" min="0" step="0.01" class="w-24 rounded-md border border-border px-1.5 py-1 text-xs text-ink">
                                                    <button type="submit" class="rounded-md bg-tone-green px-2 py-1 text-[11px] font-semibold text-white">Setujui</button>
                                                </form>
                                                <form method="POST" action="{{ route('anggaran.tolak', $row['id']) }}" onsubmit="return confirm('Tolak pengajuan ini?')">
                                                    @csrf
                                                    <button type="submit" class="rounded-md border border-tone-red px-2 py-1 text-[11px] font-semibold text-tone-red">Tolak</button>
                                                </form>
                                                <form method="POST" action="{{ route('anggaran.revisi', $row['id']) }}" onsubmit="const note = prompt('Catatan revisi (wajib diisi):'); if (!note) { return false; } this.querySelector('[name=note]').value = note; return true;">
                                                    @csrf
                                                    <input type="hidden" name="note" value="">
                                                    <button type="submit" class="rounded-md border border-tone-amber px-2 py-1 text-[11px] font-semibold text-tone-amber">Revisi</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
