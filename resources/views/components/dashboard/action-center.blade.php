@props(['items'])

<section class="mb-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5" aria-labelledby="action-center-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-bold tracking-[0.14em] text-brand-700 uppercase">Prioritas operasional</p>
            <h2 id="action-center-title" class="mt-1 text-xl font-black tracking-tight text-ink">Perlu Tindakan Hari Ini</h2>
            <p class="mt-1 text-sm text-ink-muted">Daftar ini otomatis mengikuti role dan cakupan data Anda.</p>
        </div>
        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ count($items) ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">{{ count($items) ? count($items).' kategori aktif' : 'Semua aman' }}</span>
    </div>

    @if (count($items))
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                @php $tones = ['red' => 'border-red-200 bg-red-50 text-red-700', 'amber' => 'border-amber-200 bg-amber-50 text-amber-700', 'blue' => 'border-blue-200 bg-blue-50 text-blue-700', 'green' => 'border-emerald-200 bg-emerald-50 text-emerald-700']; @endphp
                <a href="{{ $item['href'] }}" class="group flex items-start gap-3 rounded-2xl border p-4 transition hover:-translate-y-0.5 hover:shadow-md {{ $tones[$item['tone']] ?? $tones['blue'] }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/80 shadow-sm"><x-icon :name="$item['icon']" class="h-5 w-5" /></span>
                    <span class="min-w-0 flex-1"><span class="flex items-center justify-between gap-3"><strong class="text-sm">{{ $item['label'] }}</strong><span class="text-2xl font-black">{{ $item['count'] }}</span></span><span class="mt-1 block text-xs leading-5 opacity-80">{{ $item['description'] }}</span><span class="mt-2 inline-flex items-center gap-1 text-xs font-bold">Buka sekarang <x-icon name="chevron-right" class="h-3.5 w-3.5 transition-transform group-hover:translate-x-1" /></span></span>
                </a>
            @endforeach
        </div>
    @else
        <div class="mt-4 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-white"><x-icon name="check" class="h-5 w-5" /></span>
            <div><strong class="text-sm">Tidak ada pekerjaan mendesak</strong><p class="mt-0.5 text-xs text-emerald-700">Laporan, kendala, dan jadwal dalam cakupan Anda sudah tertangani.</p></div>
        </div>
    @endif
</section>
