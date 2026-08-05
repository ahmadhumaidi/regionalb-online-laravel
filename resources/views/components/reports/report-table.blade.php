@props(['rows', 'title'])

<section class="rounded-2xl border border-border bg-surface p-5">
    <h2 class="mb-4 text-base font-semibold text-ink">{{ $title }}</h2>
    @if (empty($rows))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada laporan.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs text-ink-muted">
                        <th class="py-2 pr-3 font-medium">Tanggal</th>
                        <th class="py-2 pr-3 font-medium">Wilayah / Kampus</th>
                        <th class="py-2 pr-3 font-medium">Staff</th>
                        <th class="py-2 pr-3 font-medium">Kategori</th>
                        <th class="py-2 pr-3 font-medium">Hasil</th>
                        <th class="py-2 pr-3 font-medium">Status</th>
                        <th class="py-2 font-medium">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-border/60 last:border-0">
                            <td class="py-2 pr-3 text-ink-muted">{{ $row['report_date'] }}</td>
                            <td class="py-2 pr-3 text-ink">{{ $row['unit_name'] ?: '-' }} <span class="text-xs text-ink-muted">{{ $row['wilayah'] }}</span></td>
                            <td class="py-2 pr-3 text-ink">{{ $row['staff_name'] ?: '-' }}</td>
                            <td class="py-2 pr-3 text-ink-muted">{{ $row['category'] ?: '-' }}</td>
                            <td class="py-2 pr-3 text-ink-muted">
                                {{ \Illuminate\Support\Str::limit((string) ($row['title'] ?: $row['result_text']), 60) ?: '-' }}
                                @if ($row['has_attachment'])
                                    <x-icon name="clipboard" class="ml-1 inline h-3.5 w-3.5 text-ink-muted" />
                                @endif
                            </td>
                            <td class="py-2 pr-3">
                                <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row['status'] ?: '-' }}</span>
                            </td>
                            <td class="py-2">
                                <a href="{{ route('reports.show', $row['id']) }}" class="mr-2 text-xs font-medium text-brand-600 underline">Detail</a>
                                @if ($row['can_koordinator_act'])
                                    <div class="flex flex-wrap items-center gap-1">
                                        <form method="POST" action="{{ route('reports.verifikasi', $row['id']) }}" onsubmit="return confirm('Verifikasi laporan ini?')">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-tone-green px-2 py-1 text-[11px] font-semibold text-white">Verifikasi</button>
                                        </form>
                                        <form method="POST" action="{{ route('reports.revisi', $row['id']) }}" onsubmit="const note = prompt('Catatan revisi (wajib diisi):'); if (!note) { return false; } this.querySelector('[name=note]').value = note; return true;">
                                            @csrf
                                            <input type="hidden" name="note" value="">
                                            <button type="submit" class="rounded-md border border-tone-amber px-2 py-1 text-[11px] font-semibold text-tone-amber">Revisi</button>
                                        </form>
                                    </div>
                                @elseif ($row['can_senior_act'])
                                    <div class="flex flex-wrap items-center gap-1">
                                        <form method="POST" action="{{ route('reports.setujui', $row['id']) }}" onsubmit="return confirm('Setujui laporan ini?')">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-tone-green px-2 py-1 text-[11px] font-semibold text-white">Setujui</button>
                                        </form>
                                        <form method="POST" action="{{ route('reports.tolak', $row['id']) }}" onsubmit="return confirm('Tolak laporan ini?')">
                                            @csrf
                                            <button type="submit" class="rounded-md border border-tone-red px-2 py-1 text-[11px] font-semibold text-tone-red">Tolak</button>
                                        </form>
                                        <form method="POST" action="{{ route('reports.revisi', $row['id']) }}" onsubmit="const note = prompt('Catatan revisi (wajib diisi):'); if (!note) { return false; } this.querySelector('[name=note]').value = note; return true;">
                                            @csrf
                                            <input type="hidden" name="note" value="">
                                            <button type="submit" class="rounded-md border border-tone-amber px-2 py-1 text-[11px] font-semibold text-tone-amber">Revisi</button>
                                        </form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
