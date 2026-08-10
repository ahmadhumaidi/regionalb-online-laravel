<x-layouts.app :title="$report->title ?: 'Detail Laporan'" :active="$active">
    <div class="space-y-5"><section class="rounded-2xl glass-card p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs uppercase tracking-wide text-brand-600">{{ $report->report_type }} · {{ $report->status }}</p><h2 class="mt-1 text-xl font-semibold text-ink">{{ $report->title ?: $report->campaign_name }}</h2><p class="text-sm text-ink-muted">{{ optional($report->report_date)->format('d M Y') }} · {{ $report->wilayah }} · {{ $report->unit_name }}</p></div><div class="flex gap-2">@if ($canEdit)<a href="{{ route('reports.edit', $report) }}" class="rounded-lg border border-border px-3 py-2 text-sm">Edit</a>@endif @if ($canDelete)<form method="POST" action="{{ route('reports.destroy', $report) }}" onsubmit="return confirm('Hapus laporan ini?')">@csrf @method('DELETE')<button class="rounded-lg border border-tone-red px-3 py-2 text-sm text-tone-red">Hapus</button></form>@endif</div></div>@php
    $leaderFollowUpText = trim((string) ($leaderFollowUpText ?? ''));
    $staffFollowUpText = trim((string) $report->follow_up_text);
    if ($leaderFollowUpText !== '' && $staffFollowUpText === $leaderFollowUpText) {
        $staffFollowUpText = '';
    }
    $fields = [['Staff',$report->staff_name],['Kategori',$report->category ?: $report->activity_kind],['Hasil',$report->result_text],['Kendala',$report->obstacle_text],['Tindak lanjut staff',$staffFollowUpText],['Anggaran',$report->budget_requested ? number_format($report->budget_requested,0,',','.') : null]];
    if ($report->report_type === 'ads') {
        $fields = array_merge($fields, [
            ['Platform', $report->platform],
            ['Periode Iklan', $report->ad_period],
            ['Anggaran Disetujui', number_format($report->budget_approved, 0, ',', '.')],
            ['Realisasi', number_format($report->realization_amount, 0, ',', '.')],
            ['Leads', number_format($report->leads_count, 0, ',', '.')],
            ['Closing', number_format($report->closing_count, 0, ',', '.')],
            ['CPL', number_format($report->cpl, 0, ',', '.')],
        ]);
    }
@endphp
<dl class="mt-5 grid gap-4 text-sm md:grid-cols-3">@foreach ($fields as [$label,$value])<div><dt class="text-xs text-ink-muted">{{ $label }}</dt><dd class="mt-1 text-ink">{{ $value ?: '-' }}</dd></div>@endforeach</dl>@if ($report->attachment_path || $report->insight_attachment_path)<div class="mt-5 flex flex-wrap gap-2">@if ($report->attachment_path)<a href="{{ route('reports.attachment', $report) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-l-4 border-border border-l-tone-blue bg-surface-muted/50 px-3 py-2 text-sm font-semibold text-tone-blue hover:bg-surface-muted"><x-icon name="document" class="h-4 w-4" />Lihat lampiran</a>@endif @if ($report->insight_attachment_path)<a href="{{ route('reports.insight-attachment', $report) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg border border-l-4 border-border border-l-tone-orange bg-surface-muted/50 px-3 py-2 text-sm font-semibold text-tone-orange hover:bg-surface-muted"><x-icon name="bolt" class="h-4 w-4" />Lihat bukti insight</a>@endif</div>@endif</section>
@if ($leaderFollowUpText !== '')
    <section class="rounded-2xl glass-card border-t-4 p-5" style="border-top-color: var(--color-tone-blue);">
        <h2 class="text-base font-semibold text-tone-blue">Tindak lanjut dari pimpinan</h2>
        <p class="mt-2 text-sm text-ink">{{ $leaderFollowUpText }}</p>
    </section>
@endif
@if (($actionRow['has_kendala'] ?? false) && (($actionRow['can_follow_up'] ?? false) || ($actionRow['can_mark_selesai'] ?? false)))
    <section class="rounded-2xl glass-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Tindakan Kendala</h2>
                @if (! empty($actionRow['escalated_to_label']))
                    <p class="mt-1 text-sm text-ink-muted">Dieskalasi ke {{ $actionRow['escalated_to_label'] }}</p>
                @endif
            </div>
            @if ($actionRow['can_mark_selesai'] ?? false)
                <form method="POST" action="{{ route('reports.selesai-kendala', $report) }}" onsubmit="return confirm('Tandai laporan ini selesai?')">
                    @csrf
                    <button type="submit" class="rounded-lg bg-tone-purple px-3 py-2 text-sm font-semibold text-white">Selesai</button>
                </form>
            @endif
        </div>
        @if ($actionRow['can_follow_up'] ?? false)
            <form method="POST" action="{{ route('reports.tindak-lanjut', $report) }}" class="mt-4 grid gap-3 md:grid-cols-3 md:items-end">
                @csrf
                <label class="grid gap-1 text-sm text-ink">
                    <span class="text-xs font-medium text-ink-muted">Saran tindak lanjut</span>
                    <textarea name="saran_tindak_lanjut" rows="3" class="rounded-lg border-border bg-surface-muted px-3 py-2" placeholder="Tulis tindakan atau arahan untuk kendala ini"></textarea>
                </label>
                @if (($actionRow['escalation_options'] ?? []) !== [])
                    <label class="grid gap-1 text-sm text-ink">
                        <span class="text-xs font-medium text-ink-muted">Eskalasi</span>
                        <select name="eskalasi_ke" class="rounded-lg border-border bg-surface-muted px-3 py-2">
                            <option value="">Tidak eskalasi</option>
                            @foreach ($actionRow['escalation_options'] as $option)
                                <option value="{{ $option }}">Eskalasi ke {{ \App\Support\RsmRole::label($option) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Tindak Lanjuti</button>
            </form>
        @endif
    </section>
@endif
@if ($report->report_type === 'ads')
    <section id="data-hasil-iklan" class="rounded-2xl glass-card p-5">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div><h2 class="text-base font-semibold text-ink">Data Hasil Iklan</h2><p class="mt-1 text-sm text-ink-muted">Isi dari file XLS yang diupload pada laporan iklan</p></div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" form="ad-leads-update-form" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Simpan Perubahan</button>
                <a href="{{ route('anggaran.leads.template') }}" class="rounded-lg border border-border px-3 py-2 text-sm">Download Template .xlsx</a>
            </div>
        </div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('anggaran.leads.upload', $report) }}" class="mb-4 flex flex-wrap items-center gap-2">
            @csrf
            <input type="file" name="ad_leads_file" accept=".xls,.xlsx" required class="rounded-lg border-border bg-surface-muted text-sm">
            <button type="submit" class="rounded-lg border border-border px-3 py-2 text-sm">Upload / Ganti Data</button>
        </form>
        <form id="ad-leads-update-form" method="POST" action="{{ route('anggaran.leads.update', $report) }}">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1080px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs text-ink-muted">
                            <th class="py-2 pr-3 font-medium">Nama Lead</th>
                            <th class="py-2 pr-3 font-medium">No. HP/WA</th>
                            <th class="py-2 pr-3 font-medium">Email</th>
                            <th class="py-2 pr-3 font-medium">Kampus</th>
                            <th class="py-2 pr-3 font-medium">Jurusan</th>
                            <th class="py-2 pr-3 font-medium">Kota Asal</th>
                            <th class="py-2 pr-3 font-medium">Follow Up</th>
                            <th class="py-2 pr-3 font-medium">Status Closing</th>
                            <th class="py-2 font-medium">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($adLeads as $lead)
                            @php $isClosing = mb_strtolower(trim((string) $lead->closing_status)) === 'closing'; @endphp
                            <tr class="border-b border-border/60">
                                <td class="py-2 pr-3 text-ink">{{ $lead->lead_name ?: '-' }}</td>
                                <td class="py-2 pr-3 text-ink-muted">{{ $lead->whatsapp ?: '-' }}</td>
                                <td class="py-2 pr-3 text-ink-muted">{{ $lead->email ?: '-' }}</td>
                                <td class="py-2 pr-3 text-ink-muted">{{ $lead->campus_name ?: '-' }}</td>
                                <td class="py-2 pr-3 text-ink-muted">{{ $lead->major_name ?: '-' }}</td>
                                <td class="py-2 pr-3 text-ink-muted">{{ $lead->origin_city ?: '-' }}</td>
                                <td class="py-2 pr-3 text-ink-muted">{{ $lead->follow_up_result ?: '-' }}</td>
                                <td class="py-2 pr-3">
                                    <select name="lead_status[{{ $lead->id }}]" class="rounded-md border px-1.5 py-1 text-xs {{ $isClosing ? 'border-tone-green bg-tone-green/10 font-semibold text-tone-green' : 'border-border text-ink' }}">
                                        @foreach (['Belum closing', 'Potensi closing', 'Closing', 'Herregistrasi', 'Tidak closing'] as $option)
                                            <option @selected(mb_strtolower((string) $lead->closing_status) === mb_strtolower($option))>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="py-2">
                                    <textarea name="lead_notes[{{ $lead->id }}]" rows="2" class="w-full rounded-md border border-border px-1.5 py-1 text-xs text-ink">{{ $lead->notes }}</textarea>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-6 text-center text-sm text-ink-muted">Belum ada data hasil iklan yang diupload untuk laporan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    </section>
@endif
<section class="rounded-2xl glass-card p-5"><h2 class="text-base font-semibold text-ink">Riwayat</h2><div class="mt-3 space-y-3">@forelse ($logs as $log)<div class="border-l-2 border-brand-200 pl-3 text-sm"><p class="font-medium text-ink">{{ $log->action_name }} @if ($log->old_status) · {{ $log->old_status }} → {{ $log->new_status }}@endif</p><p class="text-xs text-ink-muted">{{ $log->actor_name }} · {{ optional($log->created_at)->format('d M Y H:i') }}</p></div>@empty<p class="text-sm text-ink-muted">Belum ada riwayat.</p>@endforelse</div></section></div>
</x-layouts.app>
