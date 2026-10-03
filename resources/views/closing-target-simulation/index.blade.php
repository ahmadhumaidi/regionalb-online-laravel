<x-layouts.app title="Simulasi Target Closing" active="closing-target-simulation">
    <section class="rounded-2xl glass-card p-4 sm:p-5">
        <form method="GET" x-data="{ campusOpen: false, campusSearch: '' }" class="grid gap-4">
            <input type="hidden" name="campuses_present" value="1">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="grid gap-1.5 text-sm font-medium text-ink">Bulan awal<input type="month" name="from_month" value="{{ $fromMonth }}" class="rounded-lg border-border bg-surface-muted"></label>
                <label class="grid gap-1.5 text-sm font-medium text-ink">Bulan akhir<input type="month" name="to_month" value="{{ $toMonth }}" class="rounded-lg border-border bg-surface-muted"></label>
                <label class="grid gap-1.5 text-sm font-medium text-ink">Target closing periode<input type="number" name="target" min="0" max="10000000" value="{{ $target }}" class="rounded-lg border-border bg-surface-muted" inputmode="numeric"></label>
                <div class="grid gap-1.5 text-sm font-medium text-ink">Kampus<button type="button" @click="campusOpen = ! campusOpen" class="flex min-h-[42px] items-center justify-between rounded-lg border border-border bg-surface-muted px-3 text-left font-normal"><span>{{ count($selectedCampuses) }} kampus dipilih</span><x-icon name="chevron-down" class="h-4 w-4" /></button></div>
            </div>

            <div x-show="campusOpen" x-cloak @click.outside="campusOpen = false" class="rounded-xl border border-border bg-surface p-3 shadow-lg">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <input x-model="campusSearch" type="search" placeholder="Cari kampus atau regional..." class="w-full rounded-lg border-border bg-surface-muted sm:max-w-sm">
                    <p class="text-xs text-ink-muted">Maksimal 20 kampus per simulasi</p>
                </div>
                <div class="grid max-h-72 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($availableCampuses as $campus)
                        <label x-show="campusSearch === '' || @js(mb_strtolower($campus['name'].' '.$campus['regional'])).includes(campusSearch.toLowerCase())" class="flex cursor-pointer items-start gap-2 rounded-lg border border-border/70 p-2.5 text-sm hover:bg-surface-muted">
                            <input type="checkbox" name="campuses[]" value="{{ $campus['name'] }}" @checked(in_array($campus['name'], $selectedCampuses, true)) class="mt-0.5 rounded border-border text-brand-600">
                            <span><strong class="block font-medium text-ink">{{ $campus['name'] }}</strong><span class="text-xs text-ink-muted">{{ $campus['regional'] ?: 'Regional tidak tercatat' }}</span></span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:opacity-90">Hitung simulasi</button>
                <span class="text-xs text-ink-muted">Sumber: Closing Kampus Regional · bulan tanpa data dihitung 0.</span>
            </div>
        </form>
    </section>

    @if ($selectedCampuses === [])
        <section class="mt-5 rounded-2xl glass-card p-8 text-center"><x-icon name="chart-bar" class="mx-auto h-9 w-9 text-ink-muted" /><h2 class="mt-3 font-semibold text-ink">Pilih minimal satu kampus</h2><p class="mt-1 text-sm text-ink-muted">Data pencapaian dan pembagian target akan muncul di sini.</p></section>
    @else
        <section class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl glass-card p-4"><p class="text-xs text-ink-muted">Kampus dianalisis</p><strong class="mt-1 block text-2xl text-ink">{{ count($selectedCampuses) }}</strong></article>
            <article class="rounded-2xl glass-card p-4"><p class="text-xs text-ink-muted">Pencapaian historis</p><strong class="mt-1 block text-2xl text-ink">{{ number_format($grandTotal, 0, ',', '.') }}</strong></article>
            <article class="rounded-2xl glass-card p-4"><p class="text-xs text-ink-muted">Target dibagikan</p><strong class="mt-1 block text-2xl text-brand-600">{{ number_format($target, 0, ',', '.') }}</strong></article>
            <article class="rounded-2xl glass-card p-4"><p class="text-xs text-ink-muted">Bulan terkuat</p><strong class="mt-1 block text-lg text-ink">{{ $peak['label'] ?? '-' }}</strong><span class="text-xs text-ink-muted">{{ number_format($peak['share'] ?? 0, 2, ',', '.') }}% dari pencapaian</span></article>
        </section>

        <section class="mt-5 overflow-hidden rounded-2xl glass-card">
            <div class="border-b border-border px-4 py-4 sm:px-5"><h2 class="font-semibold text-ink">Porsi dan target per bulan</h2><p class="mt-1 text-sm text-ink-muted">Target dibulatkan dengan metode sisa terbesar agar totalnya tetap {{ number_format($target, 0, ',', '.') }}.</p></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-surface-muted text-xs text-ink-muted"><tr><th class="px-4 py-3">Bulan</th>@foreach ($selectedCampuses as $campus)<th class="px-3 py-3 text-right">{{ $campus }}</th>@endforeach<th class="px-3 py-3 text-right">Pencapaian</th><th class="px-3 py-3 text-right">Porsi</th><th class="px-4 py-3 text-right">Target</th></tr></thead>
                    <tbody>
                        @foreach ($months as $month)
                            <tr class="border-t border-border/70">
                                <td class="whitespace-nowrap px-4 py-3 font-medium text-ink">{{ $month['label'] }}</td>
                                @foreach ($selectedCampuses as $campus)<td class="px-3 py-3 text-right text-ink-muted">{{ number_format($month['campuses'][$campus], 0, ',', '.') }}</td>@endforeach
                                <td class="px-3 py-3 text-right font-semibold text-ink">{{ number_format($month['achievement'], 0, ',', '.') }}</td>
                                <td class="px-3 py-3 text-right"><div class="ml-auto flex w-28 items-center justify-end gap-2"><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-muted"><span class="block h-full rounded-full bg-brand-600" style="width: {{ min(100, $month['share'] * 5) }}%"></span></span><span class="w-14">{{ number_format($month['share'], 2, ',', '.') }}%</span></div></td>
                                <td class="px-4 py-3 text-right text-base font-bold text-brand-600">{{ number_format($month['target'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-border bg-surface-muted font-semibold text-ink"><tr><td class="px-4 py-3">Total</td>@foreach ($selectedCampuses as $campus)<td class="px-3 py-3 text-right">{{ number_format($campusTotals[$campus], 0, ',', '.') }}</td>@endforeach<td class="px-3 py-3 text-right">{{ number_format($grandTotal, 0, ',', '.') }}</td><td class="px-3 py-3 text-right">{{ $grandTotal > 0 ? '100,00%' : '0,00%' }}</td><td class="px-4 py-3 text-right text-brand-600">{{ number_format(array_sum(array_column($months, 'target')), 0, ',', '.') }}</td></tr></tfoot>
                </table>
            </div>
        </section>
    @endif
</x-layouts.app>
