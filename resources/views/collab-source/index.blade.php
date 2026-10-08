<x-layouts.app title="Sumber Data Collab" active="sumber-collab">
    @if(session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <section class="rounded-2xl glass-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Sumber Data Collab (cb.web.id)</h2>
                <p class="text-sm text-ink-muted">Snapshot cache terakhir tersinkron{{ $syncedAt !== '' ? ' - ' . $syncedAt . ' WIB' : ' - belum ada sinkronisasi' }}</p>
            </div>
            @if($canSync)
            <form method="POST" action="{{ route('sources.sync') }}" onsubmit="const button=this.querySelector('button'); button.disabled=true; button.querySelector('[data-label]').textContent='Sinkron…';">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-wait disabled:opacity-70" title="Sinkronkan Personalia, Collab, Absensi, dan BDC">
                    <x-icon name="calendar" class="h-4 w-4" />
                    <span data-label>Sinkron Semua</span>
                </button>
            </form>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2">
                @foreach($reportNames as $name)
                    <div class="inline-flex overflow-hidden rounded-full border {{ $name === $activeReport ? 'border-brand-600' : 'border-brand-200' }}">
                        <a
                            href="{{ route('sumber-collab', ['report' => $name]) }}"
                            class="px-3 py-1.5 text-xs font-bold {{ $name === $activeReport ? 'bg-brand-600 text-white' : 'bg-brand-50 text-brand-700 hover:bg-brand-100' }}"
                        >{{ $name }}</a>
                    </div>
                @endforeach
            </div>
            @if($monthOptions !== [])
                <form method="GET" class="flex items-center gap-2">
                    <input type="hidden" name="report" value="{{ $activeReport }}">
                    <label class="grid gap-1">
                        <span class="text-[11px] font-bold tracking-wide text-ink-muted uppercase">Periode</span>
                        <select name="month" onchange="this.form.submit()" class="min-h-[36px] rounded-lg border border-border px-2.5 py-1.5 text-sm">
                            <option value="">Live (cache terkini)</option>
                            @foreach($monthOptions as $option)
                                <option value="{{ $option['value'] }}" @selected($activeMonth === $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </form>
            @endif
        </div>

        @if($canSyncSelected)
            <form method="POST" action="{{ route('sumber-collab.sync') }}" class="mt-4 flex flex-wrap items-center gap-3" onsubmit="const button=this.querySelector('button'); button.disabled=true; button.querySelector('[data-label]').textContent='Menyinkronkan…';">
                @csrf
                <input type="hidden" name="report" value="{{ $activeReport }}">
                <button type="submit" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-wait disabled:opacity-70">
                    <span aria-hidden="true">&#8635;</span>
                    <span data-label>Sinkronkan {{ $activeReport }}</span>
                </button>
                <span class="text-xs text-ink-muted">Hanya menyinkronkan aktivitas pada tab yang sedang dibuka.</span>
            </form>
        @endif

        @if(($reportData['error'] ?? '') !== '')
            <div class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $reportData['error'] }}</div>
        @endif

        <div class="mt-4 flex flex-wrap gap-2 text-xs text-ink-muted">
            <span class="inline-flex items-center gap-1.5 rounded-lg glass-card-muted px-3 py-1.5"><b class="text-[10.5px] font-black tracking-wide text-ink-muted uppercase">Mode</b> {{ $modeLabel }}</span>
            <span class="inline-flex items-center gap-1.5 rounded-lg glass-card-muted px-3 py-1.5"><b class="text-[10.5px] font-black tracking-wide text-ink-muted uppercase">Diambil</b> {{ ($reportData['created_at'] ?? '') ?: '-' }}</span>
            <span class="inline-flex items-center gap-1.5 rounded-lg glass-card-muted px-3 py-1.5"><b class="text-[10.5px] font-black tracking-wide text-ink-muted uppercase">Cache disimpan</b> {{ ($reportData['cached_at'] ?? '') ?: '-' }}</span>
            <span class="inline-flex items-center gap-1.5 rounded-lg glass-card-muted px-3 py-1.5"><b class="text-[10.5px] font-black tracking-wide text-ink-muted uppercase">Ukuran</b> {{ number_format((int) ($reportData['row_count'] ?? 0), 0, ',', '.') }} baris &times; {{ number_format((int) ($reportData['column_count'] ?? 0), 0, ',', '.') }} kolom</span>
            @if(($reportData['source_url'] ?? '') !== '')
                <a class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 font-bold text-brand-700" href="{{ $reportData['source_url'] }}" target="_blank" rel="noopener">Buka sumber asli &#8599;</a>
            @endif
        </div>

        <div class="collab-table-scroll mt-4 max-h-[70vh] overflow-auto rounded-xl border border-border" tabindex="0" aria-label="Tabel {{ $activeReport }}; geser untuk melihat seluruh kolom">
            @php
                $tableFreezeClass = match (true) {
                    in_array($activeReport, ['Sebar Brosur', 'Pasang Spanduk', 'Canvasing'], true) => 'collab-sticky-name',
                    in_array($activeReport, ['Closing Kampus Regional', 'Herreg Kampus Regional'], true) => 'collab-sticky-campus',
                    $activeReport === 'Rekapitulasi PMB Periode Prioritas P2K' => 'collab-sticky-name collab-compact-campus',
                    $activeReport === 'Absen Staff' => 'collab-sticky-second collab-header-second-row',
                    default => 'collab-sticky-primary',
                };
            @endphp
            <table class="collab-raw-table {{ $tableFreezeClass }}">
                @if(($reportData['rows'] ?? []) === [])
                    <tbody><tr><td class="px-3 py-6 text-center text-sm text-ink-muted">Belum ada data tersinkron untuk sumber ini.</td></tr></tbody>
                @else
                    <thead>{!! $headerRowsHtml !!}</thead>
                    <tbody>{!! $dataRowsHtml !!}</tbody>
                @endif
            </table>
        </div>
    </section>
</x-layouts.app>
