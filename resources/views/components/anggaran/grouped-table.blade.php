@props(['groups'])

@php
    // 5 colors for up to 5 groups: Regional 4/5/6/7 plus "Regional B" itself
    // (super_user-created "Pengeluaran Senior Manager" reports carry
    // wilayah='Regional B', which sorts after 4-7 — see
    // AdBudgetReportsService — so it shows up as a trailing 5th group here).
    $toneNames = ['red', 'orange', 'blue-light', 'blue', 'blue-dark'];
    $gradients = [
        'linear-gradient(135deg, var(--color-tone-red) 0%, #991b1b 100%)',
        'linear-gradient(135deg, var(--color-tone-orange) 0%, #9a3412 100%)',
        'linear-gradient(135deg, var(--color-tone-blue-light) 0%, var(--color-tone-blue) 100%)',
        'linear-gradient(135deg, var(--color-tone-blue) 0%, var(--color-tone-blue-dark) 100%)',
        'linear-gradient(135deg, var(--color-tone-blue-dark) 0%, #0f172a 100%)',
    ];

    $statusTones = [
        'draft' => 'slate',
        'dikirim' => 'blue-light',
        'pengajuan' => 'amber',
        'revisi' => 'orange',
        'disetujui' => 'green',
        'transfer / invoice' => 'blue',
        'transfer-/-invoice' => 'blue',
        'selesai' => 'purple',
        'ditolak' => 'red',
    ];
@endphp

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
                    @foreach ($groups as $i => $regional)
                        @php $tone = $gradients[$i % 5]; $toneName = $toneNames[$i % 5]; @endphp
                        <tr style="background: {{ $tone }}">
                            <td colspan="2" class="py-2.5 pr-3 pl-3 font-bold text-white">
                                Regional: {{ $regional['wilayah'] }} <span class="font-normal text-white/80">({{ $regional['subtotal']['count'] }} laporan)</span>
                            </td>
                            <td class="py-2.5 pr-3 text-right font-bold text-white">{{ number_format($regional['subtotal']['requested'], 0, ',', '.') }}</td>
                            <td class="py-2.5 pr-3 text-right font-bold text-white">{{ number_format($regional['subtotal']['realization'], 0, ',', '.') }}</td>
                            <td class="py-2.5 pr-3 text-right font-bold text-white">{{ number_format($regional['subtotal']['leads'], 0, ',', '.') }}</td>
                            <td class="py-2.5 pr-3 text-right font-bold text-white">{{ number_format($regional['subtotal']['closing'], 0, ',', '.') }}</td>
                            <td class="py-2.5 pr-3 text-right font-bold text-white">{{ number_format($regional['subtotal']['cpl'], 0, ',', '.') }}</td>
                            <td class="py-2.5 pr-3"></td>
                            <td class="py-2.5 pr-3"></td>
                        </tr>
                        @foreach ($regional['campuses'] as $campus)
                            <tr class="border-b border-l-4 border-border" style="border-left-color: var(--color-tone-{{ $toneName }}); background: color-mix(in srgb, var(--color-tone-{{ $toneName }}) 16%, var(--color-surface))">
                                <td colspan="2" class="py-1.5 pr-3 pl-4 font-semibold text-ink">
                                    {{ $campus['label'] }} <span class="font-normal text-ink-muted">({{ $campus['subtotal']['count'] }})</span>
                                </td>
                                <td class="py-1.5 pr-3 text-right font-medium text-ink">{{ number_format($campus['subtotal']['requested'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right font-medium text-ink">{{ number_format($campus['subtotal']['realization'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right font-medium text-ink">{{ number_format($campus['subtotal']['leads'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right font-medium text-ink">{{ number_format($campus['subtotal']['closing'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right font-medium text-ink">{{ number_format($campus['subtotal']['cpl'], 0, ',', '.') }}</td>
                                <td class="py-1.5"></td>
                                <td class="py-1.5"></td>
                            </tr>
                            @foreach ($campus['rows'] as $row)
                                <tr class="border-b border-border/60 bg-white">
                                    <td class="py-2 pr-3 pl-8 text-ink">{{ $row['campaign_name'] ?: '-' }} <span class="text-xs text-ink-muted">{{ $row['platform'] }}</span></td>
                                    <td class="py-2 pr-3 text-xs whitespace-nowrap text-ink-muted">{{ $row['report_date'] }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['budget_requested'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['realization_amount'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['leads_count'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['closing_count'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['cpl'], 0, ',', '.') }}</td>
                                    @php $statusTone = $statusTones[mb_strtolower(trim((string) $row['status']))] ?? 'slate'; @endphp
                                    <td class="py-2 pr-3">
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background: color-mix(in srgb, var(--color-tone-{{ $statusTone }}) 18%, transparent); color: var(--color-tone-{{ $statusTone }})">{{ $row['status'] ?: '-' }}</span>
                                    </td>
                                    <td class="py-2">
                                        <div class="flex flex-wrap items-center gap-1">
                                            <a href="{{ route('reports.show', $row['id']) }}" title="Lihat" aria-label="Lihat" class="rounded-md border border-border p-1 text-ink-muted hover:bg-surface-muted hover:text-ink"><x-icon name="eye" class="h-3.5 w-3.5" /></a>
                                            @if ($row['has_attachment'])
                                                <a href="{{ route('reports.attachment', $row['id']) }}" target="_blank" rel="noopener" title="Lihat invoice/bukti transfer" aria-label="Lihat invoice" class="rounded-md border border-l-2 border-border border-l-tone-blue p-1 text-tone-blue hover:bg-surface-muted"><x-icon name="document" class="h-3.5 w-3.5" /></a>
                                            @endif
                                            @if ($row['has_insight_attachment'])
                                                <a href="{{ route('reports.insight-attachment', $row['id']) }}" target="_blank" rel="noopener" title="Lihat bukti insight/pengeluaran iklan" aria-label="Lihat insight" class="rounded-md border border-l-2 border-border border-l-tone-orange p-1 text-tone-orange hover:bg-surface-muted"><x-icon name="bolt" class="h-3.5 w-3.5" /></a>
                                            @endif
                                            @if ($row['has_ad_leads'])
                                                <a href="{{ route('reports.show', $row['id']) }}#data-hasil-iklan" title="Lihat data hasil iklan" aria-label="Lihat data hasil iklan" class="rounded-md border border-l-2 border-border border-l-tone-green p-1 text-tone-green hover:bg-surface-muted"><x-icon name="chart-bar" class="h-3.5 w-3.5" /></a>
                                            @endif
                                            @if ($row['can_edit'])
                                                <a href="{{ route('reports.edit', $row['id']) }}" title="Edit" aria-label="Edit" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="edit" class="h-3.5 w-3.5" /></a>
                                            @endif
                                            @if ($row['can_delete'])
                                                <form method="POST" action="{{ route('reports.destroy', $row['id']) }}" data-preserve-scroll onsubmit="return confirm('Hapus laporan ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Hapus" aria-label="Hapus" class="rounded-md border border-tone-red p-1 text-tone-red"><x-icon name="trash" class="h-3.5 w-3.5" /></button>
                                                </form>
                                            @endif
                                            @if ($row['can_review'])
                                                <form method="POST" action="{{ route('anggaran.setujui', $row['id']) }}" class="flex items-center gap-1" onsubmit="return confirm('Setujui pengajuan ini?')">
                                                    @csrf
                                                    <input type="number" name="budget_approved" value="{{ number_format($row['budget_requested'], 2, '.', '') }}" min="0" step="0.01" class="w-20 rounded-md border border-border px-1.5 py-1 text-xs text-ink">
                                                    <button type="submit" title="Setujui" aria-label="Setujui" class="rounded-md border border-tone-green p-1 text-tone-green"><x-icon name="check" class="h-3.5 w-3.5" /></button>
                                                </form>
                                                <form method="POST" action="{{ route('anggaran.tolak', $row['id']) }}" onsubmit="return confirm('Tolak pengajuan ini?')">
                                                    @csrf
                                                    <button type="submit" title="Tolak" aria-label="Tolak" class="rounded-md border border-tone-red p-1 text-tone-red"><x-icon name="close" class="h-3.5 w-3.5" /></button>
                                                </form>
                                                <form method="POST" action="{{ route('anggaran.revisi', $row['id']) }}" onsubmit="const note = prompt('Catatan revisi (wajib diisi):'); if (!note) { return false; } this.querySelector('[name=note]').value = note; return true;">
                                                    @csrf
                                                    <input type="hidden" name="note" value="">
                                                    <button type="submit" title="Revisi" aria-label="Revisi" class="rounded-md border border-tone-amber p-1 text-tone-amber"><x-icon name="warning" class="h-3.5 w-3.5" /></button>
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
