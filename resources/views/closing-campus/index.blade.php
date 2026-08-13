<x-layouts.app title="Pencapaian Kampus" active="closing-kampus">
    @php
        $liveActive = request('periode') === 'daily';
        $currentMonthValue = substr($filters['date_from'], 0, 7);
        $periodeMonthOptions = [];
        $periodeCursor = \Illuminate\Support\Carbon::now('Asia/Jakarta')->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $periodeMonthOptions[] = ['value' => $periodeCursor->format('Y-m'), 'label' => $periodeCursor->locale('id')->translatedFormat('F Y')];
            $periodeCursor = $periodeCursor->copy()->subMonthNoOverflow();
        }
    @endphp
    <section class="rounded-2xl glass-card p-5">
        <form method="GET" class="mb-3 flex items-center gap-2">
            @foreach (request()->except(['periode', 'date_from', 'date_to']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <input type="hidden" name="periode" value="{{ $liveActive ? 'daily' : 'monthly' }}">
            <input type="hidden" name="date_from" value="{{ $currentMonthValue }}">
            <label class="grid gap-1">
                <span class="text-[11px] font-bold tracking-wide text-ink-muted uppercase">Periode</span>
                <select onchange="const o=this.options[this.selectedIndex];this.form.periode.value=o.dataset.periode;this.form.date_from.value=o.dataset.month;this.form.submit();" class="min-h-[36px] rounded-lg border border-border px-2.5 py-1.5 text-sm">
                    <option data-periode="daily" data-month="" @selected($liveActive)>Live (Hari Ini)</option>
                    @foreach ($periodeMonthOptions as $option)
                        <option data-periode="monthly" data-month="{{ $option['value'] }}" @selected(! $liveActive && $currentMonthValue === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </label>
        </form>
        <form method="GET" class="grid gap-3 md:grid-cols-5">
            <input type="hidden" name="periode" value="{{ $liveActive ? 'daily' : 'monthly' }}">
            <input type="hidden" name="date_from" value="{{ $currentMonthValue }}">
            <label class="grid gap-1 text-xs text-ink-muted">Wilayah<select name="wilayah" class="rounded-lg border-border bg-surface-muted"><option value="">Semua</option>@foreach($regionals as $regional)<option @selected($filters['wilayah']===$regional)>{{ $regional }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Unit/Kampus<select name="unit_name" class="rounded-lg border-border bg-surface-muted"><option value="">Semua</option>@foreach($references['campuses'] as $campusOption)<option value="{{ $campusOption['label'] }}" @selected($filters['unit_name']===$campusOption['label'])>{{ $campusOption['label'] }}</option>@endforeach</select></label>
            <div class="flex items-end"><button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Terapkan</button></div>
        </form>
    </section>
    <section class="mt-5 rounded-2xl glass-card p-5"><div class="mb-4 flex items-center justify-between"><div><h2 class="text-base font-semibold text-ink">Pencapaian Kampus</h2><p class="text-xs text-ink-muted">Semua kampus · Closing Kampus Regional · {{ $filters['date_from'] }} s/d {{ $filters['date_to'] }}</p></div><a href="{{ route('dashboard') }}" class="text-sm text-brand-600 underline">Kembali ke Dashboard</a></div><div class="grid gap-4 lg:grid-cols-2">@foreach($regionals as $regional)<div class="rounded-xl border border-border/70 p-4"><div class="mb-3 flex justify-between"><strong>{{ $regional }}</strong><span class="text-sm text-ink-muted">{{ number_format(collect($grouped[$regional]??[])->sum('registrasi'),0,',','.') }} closing</span></div>@forelse($grouped[$regional]??[] as $row)<div class="mb-3"><div class="flex justify-between text-sm"><span>{{ $row['unit'] }}</span><b>{{ number_format($row['registrasi'],0,',','.') }}</b></div><div class="mt-1 h-1.5 rounded-full bg-surface-muted"><div class="h-full rounded-full bg-brand-600 progress-fill" style="width: {{ $campus['max_value']>0?min(100,max(3,round($row['registrasi']/$campus['max_value']*100))):0 }}%"></div></div></div>@empty<p class="text-sm text-ink-muted">Belum ada closing kampus.</p>@endforelse</div>@endforeach</div></section>
</x-layouts.app>
