<x-layouts.app title="Laporan & Rekap" active="rekap">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-2xl glass-card p-5">
        @php
            $liveActive = request('periode') === 'daily';
            $yesterdayActive = request('periode') === 'yesterday';
            $periodeValue = $liveActive ? 'daily' : ($yesterdayActive ? 'yesterday' : 'monthly');
            $currentMonthValue = substr($filters['date_from'], 0, 7);
            $periodeMonthOptions = [];
            $periodeCursor = \Illuminate\Support\Carbon::now('Asia/Jakarta')->startOfMonth();
            for ($i = 0; $i < 24; $i++) {
                $periodeMonthOptions[] = ['value' => $periodeCursor->format('Y-m'), 'label' => $periodeCursor->locale('id')->translatedFormat('F Y')];
                $periodeCursor = $periodeCursor->copy()->subMonthNoOverflow();
            }
        @endphp
        <form method="GET" class="mb-3 flex items-center gap-2">
            @foreach (request()->except(['periode', 'date_from', 'date_to']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="periode" value="{{ $periodeValue }}">
            <input type="hidden" name="date_from" value="{{ $currentMonthValue }}">
            <label class="grid gap-1">
                <span class="text-[11px] font-bold tracking-wide text-ink-muted uppercase">Periode</span>
                <select onchange="const o=this.options[this.selectedIndex];this.form.periode.value=o.dataset.periode;this.form.date_from.value=o.dataset.month;this.form.submit();" class="min-h-[36px] rounded-lg border border-border px-2.5 py-1.5 text-sm">
                    <option data-periode="daily" data-month="" @selected($liveActive)>Live (Hari Ini)</option>
                    @if ($type === 'pencapaian')
                        <option data-periode="yesterday" data-month="" @selected($yesterdayActive)>Kemarin</option>
                    @endif
                    @foreach ($periodeMonthOptions as $option)
                        <option data-periode="monthly" data-month="{{ $option['value'] }}" @selected(! $liveActive && ! $yesterdayActive && $currentMonthValue === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </form>
        <form method="GET" class="grid gap-3 md:grid-cols-3 lg:grid-cols-6">
            <input type="hidden" name="periode" value="{{ $periodeValue }}">
            <input type="hidden" name="date_from" value="{{ $currentMonthValue }}">
            <label class="grid gap-1 text-xs text-ink-muted">Wilayah<select name="wilayah" class="rounded-lg border-border bg-surface-muted"><option value="">Semua</option>@foreach ($references['regionals'] as $value)<option value="{{ $value }}" @selected($filters['wilayah'] === $value)>{{ $value }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Unit<select name="unit_name" class="rounded-lg border-border bg-surface-muted"><option value="">Semua</option>@foreach ($references['campuses'] as $campus)<option value="{{ $campus['label'] }}" @selected($filters['unit_name'] === $campus['label'])>{{ $campus['label'] }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Jenis<select name="rekap_type" class="rounded-lg border-border bg-surface-muted"><option value="all" @selected($type === 'all')>Semua laporan</option><option value="marketing" @selected($type === 'marketing')>Marketing</option><option value="ads" @selected($type === 'ads')>Iklan</option><option value="other" @selected($type === 'other')>Aktivitas lain</option><option value="pencapaian" @selected($type === 'pencapaian')>Report Pencapaian</option></select></label>
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Terapkan</button>
                <a href="{{ route('rekap.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rounded-lg border border-border px-3 py-2 text-sm">CSV</a>
                <a href="{{ route('rekap.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="rounded-lg border border-border px-3 py-2 text-sm">Excel</a>
                <a href="{{ route('rekap.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="rounded-lg border border-border px-3 py-2 text-sm">PDF</a>
            </div>
        </form>
    </section>
    @if ($type === 'pencapaian')
        <section class="mt-5 rounded-2xl glass-card p-5" id="pencapaian-snapshot">
            <x-rekap.achievement-report :report="$achievementReport" />
        </section>

        @if (auth()->user()->role === 'super_user')
            <section class="mt-5 rounded-2xl glass-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-ink">Bahan WhatsApp Otomatis</h2>
                        <p class="text-xs text-ink-muted">Ringkasan pencapaian sesuai periode & filter terpilih di atas, siap salin ke WhatsApp.</p>
                    </div>
                    <form method="POST" action="{{ route('rekap.whatsapp') }}">
                        @csrf
                        <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
                        <input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">
                        <input type="hidden" name="wilayah" value="{{ $filters['wilayah'] }}">
                        <input type="hidden" name="unit_name" value="{{ $filters['unit_name'] }}">
                        <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Generate Sekarang</button>
                    </form>
                </div>
                @if ($whatsappArtifact['text'] ?? null)
                    @php $period = $whatsappArtifact['period'] ?? null; @endphp
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-ink-muted">
                        <span>
                            @if ($period)
                                Periode: {{ \Illuminate\Support\Carbon::parse($period['date_from'])->format('d/m/Y') }} s/d {{ \Illuminate\Support\Carbon::parse($period['date_to'])->format('d/m/Y') }}
                            @endif
                        </span>
                        <span>Jam generate: {{ $whatsappArtifact['generated_at'] ?? '-' }} WIB</span>
                        <button type="button" onclick="captureAchievementSnapshot('pencapaian-snapshot', 'open', this)" class="font-semibold text-brand-600 underline">Buka gambar</button>
                    </div>
                    <textarea id="whatsapp-artifact-text" readonly rows="10" class="mt-1 w-full rounded-lg border-border bg-surface text-sm">{{ $whatsappArtifact['text'] }}</textarea>
                    <div class="mt-3 flex flex-wrap items-center justify-end gap-2 border-t border-border/60 pt-3">
                        <button type="button" onclick="captureAchievementSnapshot('pencapaian-snapshot', 'download', this)" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Download Gambar</button>
                        <button type="button" onclick="const t=document.getElementById('whatsapp-artifact-text'); t.select(); navigator.clipboard.writeText(t.value); this.textContent='Tersalin!'; setTimeout(() => this.textContent='Copy Text Otomatis', 1500);" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-ink">Copy Text Otomatis</button>
                    </div>
                @else
                    <p class="mt-3 text-sm text-ink-muted">Belum ada bahan otomatis. Klik Generate Sekarang.</p>
                @endif
            </section>
        @endif
    @else
        <section class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">@foreach ([['Laporan',$recap['summary']['count']],['Leads',$recap['summary']['leads']],['Closing',$recap['summary']['closing']],['Anggaran','Rp '.number_format($recap['summary']['requested'],0,',','.')],['Realisasi','Rp '.number_format($recap['summary']['realization'],0,',','.')]] as [$label,$value])<div class="rounded-2xl glass-card p-4"><p class="text-xs text-ink-muted">{{ $label }}</p><p class="mt-1 text-lg font-semibold text-ink">{{ $value }}</p></div>@endforeach</section>
        <section class="mt-5 overflow-x-auto rounded-2xl glass-card p-5"><table class="w-full min-w-[1100px] text-left text-sm"><thead><tr class="border-b border-border text-xs text-ink-muted"><th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">Jenis</th><th class="py-2 pr-3">Wilayah/Unit</th><th class="py-2 pr-3">Staff</th><th class="py-2 pr-3">Judul</th><th class="py-2 pr-3">Leads</th><th class="py-2 pr-3">Anggaran</th><th class="py-2 pr-3">Status</th><th class="py-2">Aksi</th></tr></thead><tbody>@forelse ($recap['rows'] as $row)<tr class="border-b border-border/60"><td class="py-2 pr-3">{{ $row['report_date'] }}</td><td class="py-2 pr-3">{{ $row['report_type'] }}</td><td class="py-2 pr-3">{{ $row['wilayah'] }}<br><span class="text-xs text-ink-muted">{{ $row['unit_name'] }}</span></td><td class="py-2 pr-3">{{ $row['staff_name'] }}</td><td class="py-2 pr-3">{{ $row['title'] ?: '-' }}</td><td class="py-2 pr-3">{{ $row['leads_count'] }}</td><td class="py-2 pr-3">Rp {{ number_format($row['budget_requested'],0,',','.') }}</td><td class="py-2 pr-3">{{ $row['status'] }}</td><td class="py-2"><div class="flex flex-wrap items-center gap-1"><a href="{{ route('reports.show', $row['id']) }}" title="Lihat" aria-label="Lihat" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="eye" class="h-3.5 w-3.5" /></a>@if ($row['can_edit'])<a href="{{ route('reports.edit', $row['id']) }}" title="Edit" aria-label="Edit" class="rounded-md border border-border p-1 text-ink-muted hover:text-ink"><x-icon name="edit" class="h-3.5 w-3.5" /></a>@endif @if ($row['can_delete'])<form method="POST" action="{{ route('reports.destroy', $row['id']) }}" onsubmit="return confirm('Hapus laporan ini?')">@csrf @method('DELETE')<button type="submit" title="Hapus" aria-label="Hapus" class="rounded-md border border-tone-red p-1 text-tone-red"><x-icon name="trash" class="h-3.5 w-3.5" /></button></form>@endif</div></td></tr>@empty<tr><td colspan="9" class="py-8 text-center text-ink-muted">Belum ada data pada filter ini.</td></tr>@endforelse</tbody></table></section>
    @endif
</x-layouts.app>
