@props(['rows', 'title'])

@php
    $statusTones = [
        'draft' => 'slate',
        'dikirim' => 'blue-light',
        'diverifikasi' => 'blue',
        'disetujui' => 'green',
        'ditolak' => 'red',
        'revisi' => 'orange',
        'ditindak lanjuti' => 'amber',
        'selesai' => 'purple',
    ];
@endphp

<section class="rounded-2xl glass-card p-5">
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
                                @php $statusTone = $statusTones[mb_strtolower(trim((string) $row['status']))] ?? 'slate'; @endphp
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background: color-mix(in srgb, var(--color-tone-{{ $statusTone }}) 18%, transparent); color: var(--color-tone-{{ $statusTone }})">{{ $row['status'] ?: '-' }}</span>
                                @if (! empty($row['escalated_to_label']))
                                    <span class="mt-1 block text-[10px] text-ink-muted">Dieskalasi ke {{ $row['escalated_to_label'] }}</span>
                                @endif
                            </td>
                            <td class="py-2">
                                <div class="flex flex-wrap items-center gap-1">
                                    <a href="{{ route('reports.show', $row['id']) }}" title="Lihat" aria-label="Lihat" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="eye" class="h-3.5 w-3.5" /></a>
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
                                    @if ($row['can_koordinator_act'])
                                        <form method="POST" action="{{ route('reports.verifikasi', $row['id']) }}" onsubmit="return confirm('Verifikasi laporan ini?')">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-tone-green px-2 py-1 text-[11px] font-semibold text-white">Verifikasi</button>
                                        </form>
                                        <form method="POST" action="{{ route('reports.revisi', $row['id']) }}" onsubmit="const note = prompt('Catatan revisi (wajib diisi):'); if (!note) { return false; } this.querySelector('[name=note]').value = note; return true;">
                                            @csrf
                                            <input type="hidden" name="note" value="">
                                            <button type="submit" class="rounded-md border border-tone-amber px-2 py-1 text-[11px] font-semibold text-tone-amber">Revisi</button>
                                        </form>
                                    @elseif ($row['can_senior_act'])
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
                                    @endif
                                    @if ($row['can_follow_up'])
                                        <details class="text-xs">
                                            <summary class="cursor-pointer list-none rounded-md border border-tone-blue px-2 py-1 font-medium text-tone-blue hover:bg-surface-muted">Tindak Lanjuti</summary>
                                            <form method="POST" action="{{ route('reports.tindak-lanjut', $row['id']) }}" class="mt-2 grid gap-1">
                                                @csrf
                                                <textarea name="saran_tindak_lanjut" placeholder="Saran tindak lanjut" rows="2" class="w-56 rounded border-border px-1 text-xs"></textarea>
                                                @if ($row['escalation_options'] !== [])
                                                    <select name="eskalasi_ke" class="rounded border-border text-xs">
                                                        <option value="">-- Tidak eskalasi --</option>
                                                        @foreach ($row['escalation_options'] as $option)
                                                            <option value="{{ $option }}">Eskalasi ke {{ \App\Support\RsmRole::label($option) }}</option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                                <button type="submit" class="mt-1 rounded-md bg-brand-600 px-2 py-1 text-left text-xs font-semibold text-white">Kirim</button>
                                            </form>
                                        </details>
                                    @endif
                                    @if ($row['can_mark_selesai'])
                                        <form method="POST" action="{{ route('reports.selesai-kendala', $row['id']) }}" onsubmit="return confirm('Tandai laporan ini selesai?')">
                                            @csrf
                                            <button type="submit" class="rounded-md bg-tone-purple px-2 py-1 text-[11px] font-semibold text-white">Selesai</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
