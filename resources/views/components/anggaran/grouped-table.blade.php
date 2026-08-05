@props(['groups'])

<section class="rounded-2xl border border-border bg-surface p-5">
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
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">Rp {{ number_format($regional['subtotal']['requested'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">Rp {{ number_format($regional['subtotal']['realization'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">{{ number_format($regional['subtotal']['leads'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">{{ number_format($regional['subtotal']['closing'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-brand-700">Rp {{ number_format($regional['subtotal']['cpl'], 0, ',', '.') }}</td>
                            <td class="py-2"></td>
                            <td class="py-2"></td>
                        </tr>
                        @foreach ($regional['campuses'] as $campus)
                            <tr class="bg-surface-muted/70">
                                <td colspan="2" class="py-1.5 pr-3 pl-4 font-medium text-ink">
                                    {{ $campus['label'] }} <span class="font-normal text-ink-muted">({{ $campus['subtotal']['count'] }})</span>
                                </td>
                                <td class="py-1.5 pr-3 text-right text-ink">Rp {{ number_format($campus['subtotal']['requested'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right text-ink">Rp {{ number_format($campus['subtotal']['realization'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right text-ink">{{ number_format($campus['subtotal']['leads'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right text-ink">{{ number_format($campus['subtotal']['closing'], 0, ',', '.') }}</td>
                                <td class="py-1.5 pr-3 text-right text-ink">Rp {{ number_format($campus['subtotal']['cpl'], 0, ',', '.') }}</td>
                                <td class="py-1.5"></td>
                                <td class="py-1.5"></td>
                            </tr>
                            @foreach ($campus['rows'] as $row)
                                <tr class="border-b border-border/60">
                                    <td class="py-2 pr-3 pl-8 text-ink">{{ $row['campaign_name'] ?: '-' }} <span class="text-xs text-ink-muted">{{ $row['platform'] }}</span></td>
                                    <td class="py-2 pr-3 text-ink-muted">{{ $row['report_date'] }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">Rp {{ number_format($row['budget_requested'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">Rp {{ number_format($row['realization_amount'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['leads_count'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['closing_count'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3 text-right text-ink">Rp {{ number_format($row['cpl'], 0, ',', '.') }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row['status'] ?: '-' }}</span>
                                        @if ($row['has_attachment'])
                                            <x-icon name="clipboard" class="ml-1 inline h-3.5 w-3.5 text-ink-muted" />
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        @if ($row['can_review'])
                                            <div class="flex flex-wrap items-center gap-1">
                                                <form method="POST" action="{{ route('anggaran.setujui', $row['id']) }}" class="flex items-center gap-1" onsubmit="return confirm('Setujui pengajuan ini?')">
                                                    @csrf
                                                    <input type="number" name="budget_approved" value="{{ (int) $row['budget_requested'] }}" min="0" step="1" class="w-24 rounded-md border border-border px-1.5 py-1 text-xs text-ink">
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
                                            </div>
                                        @elseif ($row['can_complete'])
                                            <form method="POST" action="{{ route('anggaran.selesai', $row['id']) }}" onsubmit="return confirm('Tandai laporan ini selesai?')">
                                                @csrf
                                                <button type="submit" class="rounded-md bg-brand-600 px-2 py-1 text-[11px] font-semibold text-white">Selesai</button>
                                            </form>
                                        @endif
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
