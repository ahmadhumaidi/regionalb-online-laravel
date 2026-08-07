<x-layouts.app title="Laporan & Rekap" active="rekap">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if (auth()->user()->role === 'super_user')
        <section class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Bahan WhatsApp Otomatis</h2>
                    <p class="text-xs text-ink-muted">Ringkasan pencapaian hari ini, siap salin ke WhatsApp.</p>
                </div>
                <form method="POST" action="{{ route('rekap.whatsapp') }}">
                    @csrf
                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Generate Sekarang</button>
                </form>
            </div>
            @if ($whatsappArtifact['text'] ?? null)
                <p class="mt-3 text-xs text-ink-muted">Dibuat {{ $whatsappArtifact['generated_at'] ?? '-' }}</p>
                <textarea readonly rows="10" class="mt-1 w-full rounded-lg border-border bg-surface text-sm">{{ $whatsappArtifact['text'] }}</textarea>
            @else
                <p class="mt-3 text-sm text-ink-muted">Belum ada bahan otomatis. Klik Generate Sekarang.</p>
            @endif
        </section>
    @endif

    <section class="rounded-2xl border border-border bg-surface p-5">
        <form method="GET" class="grid gap-3 md:grid-cols-3 lg:grid-cols-6">
            <label class="grid gap-1 text-xs text-ink-muted">Dari<input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-lg border-border bg-surface-muted"></label>
            <label class="grid gap-1 text-xs text-ink-muted">Sampai<input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-lg border-border bg-surface-muted"></label>
            <label class="grid gap-1 text-xs text-ink-muted">Wilayah<select name="wilayah" class="rounded-lg border-border bg-surface-muted"><option value="">Semua</option>@foreach ($references['regionals'] as $value)<option value="{{ $value }}" @selected($filters['wilayah'] === $value)>{{ $value }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Unit<select name="unit_name" class="rounded-lg border-border bg-surface-muted"><option value="">Semua</option>@foreach ($references['campuses'] as $campus)<option value="{{ $campus['label'] }}" @selected($filters['unit_name'] === $campus['label'])>{{ $campus['label'] }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Jenis<select name="rekap_type" class="rounded-lg border-border bg-surface-muted"><option value="all" @selected($type === 'all')>Semua laporan</option><option value="marketing" @selected($type === 'marketing')>Marketing</option><option value="ads" @selected($type === 'ads')>Iklan</option><option value="other" @selected($type === 'other')>Aktivitas lain</option></select></label>
            <div class="flex items-end gap-2"><button class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Terapkan</button><a href="{{ route('rekap.export', request()->query()) }}" class="rounded-lg border border-border px-3 py-2 text-sm">{{ $type === 'ads' ? 'Excel' : 'CSV' }}</a></div>
        </form>
    </section>
    <section class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@foreach ([['Laporan',$recap['summary']['count']],['Leads',$recap['summary']['leads']],['Closing',$recap['summary']['closing']],['Anggaran','Rp '.number_format($recap['summary']['requested'],0,',','.')],['Realisasi','Rp '.number_format($recap['summary']['realization'],0,',','.')]] as [$label,$value])<div class="rounded-2xl border border-border bg-surface p-4"><p class="text-xs text-ink-muted">{{ $label }}</p><p class="mt-1 text-lg font-semibold text-ink">{{ $value }}</p></div>@endforeach</section>
    <section class="mt-5 overflow-x-auto rounded-2xl border border-border bg-surface p-5"><table class="w-full min-w-[1100px] text-left text-sm"><thead><tr class="border-b border-border text-xs text-ink-muted"><th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">Jenis</th><th class="py-2 pr-3">Wilayah/Unit</th><th class="py-2 pr-3">Staff</th><th class="py-2 pr-3">Judul</th><th class="py-2 pr-3">Leads</th><th class="py-2 pr-3">Anggaran</th><th class="py-2 pr-3">Status</th><th class="py-2">Aksi</th></tr></thead><tbody>@forelse ($recap['rows'] as $row)<tr class="border-b border-border/60"><td class="py-2 pr-3">{{ $row['report_date'] }}</td><td class="py-2 pr-3">{{ $row['report_type'] }}</td><td class="py-2 pr-3">{{ $row['wilayah'] }}<br><span class="text-xs text-ink-muted">{{ $row['unit_name'] }}</span></td><td class="py-2 pr-3">{{ $row['staff_name'] }}</td><td class="py-2 pr-3">{{ $row['title'] ?: '-' }}</td><td class="py-2 pr-3">{{ $row['leads_count'] }}</td><td class="py-2 pr-3">Rp {{ number_format($row['budget_requested'],0,',','.') }}</td><td class="py-2 pr-3">{{ $row['status'] }}</td><td class="py-2"><div class="flex flex-wrap items-center gap-1"><a href="{{ route('reports.show', $row['id']) }}" title="Lihat" aria-label="Lihat" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="eye" class="h-3.5 w-3.5" /></a>@if ($row['can_edit'])<a href="{{ route('reports.edit', $row['id']) }}" title="Edit" aria-label="Edit" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="edit" class="h-3.5 w-3.5" /></a>@endif @if ($row['can_delete'])<form method="POST" action="{{ route('reports.destroy', $row['id']) }}" onsubmit="return confirm('Hapus laporan ini?')">@csrf @method('DELETE')<button type="submit" title="Hapus" aria-label="Hapus" class="rounded-md border border-tone-red p-1 text-tone-red"><x-icon name="trash" class="h-3.5 w-3.5" /></button></form>@endif</div></td></tr>@empty<tr><td colspan="9" class="py-8 text-center text-ink-muted">Belum ada data pada filter ini.</td></tr>@endforelse</tbody></table></section>
</x-layouts.app>
