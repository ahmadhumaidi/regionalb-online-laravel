<x-layouts.app :title="$report->title ?: 'Detail Laporan'" :active="$active">
    <div class="space-y-5"><section class="rounded-2xl border border-border bg-surface p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs uppercase tracking-wide text-brand-600">{{ $report->report_type }} · {{ $report->status }}</p><h2 class="mt-1 text-xl font-semibold text-ink">{{ $report->title ?: $report->campaign_name }}</h2><p class="text-sm text-ink-muted">{{ optional($report->report_date)->format('d M Y') }} · {{ $report->wilayah }} · {{ $report->unit_name }}</p></div><div class="flex gap-2">@if ($canEdit)<a href="{{ route('reports.edit', $report) }}" class="rounded-lg border border-border px-3 py-2 text-sm">Edit</a>@endif @if ($canDelete)<form method="POST" action="{{ route('reports.destroy', $report) }}" onsubmit="return confirm('Hapus laporan ini?')">@csrf @method('DELETE')<button class="rounded-lg border border-tone-red px-3 py-2 text-sm text-tone-red">Hapus</button></form>@endif</div></div>@php
    $fields = [['Staff',$report->staff_name],['Kategori',$report->category ?: $report->activity_kind],['Hasil',$report->result_text],['Kendala',$report->obstacle_text],['Tindak lanjut',$report->follow_up_text],['Anggaran',$report->budget_requested ? 'Rp '.number_format($report->budget_requested,0,',','.') : null]];
    if ($report->report_type === 'ads') {
        $fields = array_merge($fields, [
            ['Platform', $report->platform],
            ['Periode Iklan', $report->ad_period],
            ['Anggaran Disetujui', 'Rp '.number_format($report->budget_approved, 0, ',', '.')],
            ['Realisasi', 'Rp '.number_format($report->realization_amount, 0, ',', '.')],
            ['Leads', number_format($report->leads_count, 0, ',', '.')],
            ['Closing', number_format($report->closing_count, 0, ',', '.')],
            ['CPL', 'Rp '.number_format($report->cpl, 0, ',', '.')],
        ]);
    }
@endphp
<dl class="mt-5 grid gap-4 text-sm md:grid-cols-3">@foreach ($fields as [$label,$value])<div><dt class="text-xs text-ink-muted">{{ $label }}</dt><dd class="mt-1 text-ink">{{ $value ?: '-' }}</dd></div>@endforeach</dl>@if ($report->attachment_path)<a class="mt-5 inline-block text-sm font-medium text-brand-600 underline" href="{{ route('reports.attachment', $report) }}">Lihat lampiran</a>@endif</section><section class="rounded-2xl border border-border bg-surface p-5"><h2 class="text-base font-semibold text-ink">Riwayat</h2><div class="mt-3 space-y-3">@forelse ($logs as $log)<div class="border-l-2 border-brand-200 pl-3 text-sm"><p class="font-medium text-ink">{{ $log->action_name }} @if ($log->old_status) · {{ $log->old_status }} → {{ $log->new_status }}@endif</p><p class="text-xs text-ink-muted">{{ $log->actor_name }} · {{ optional($log->created_at)->format('d M Y H:i') }}</p></div>@empty<p class="text-sm text-ink-muted">Belum ada riwayat.</p>@endforelse</div></section></div>
</x-layouts.app>
