<x-layouts.app title="Anggaran & Laporan Iklan" active="anggaran">
    @if (auth()->user()->role === 'koordinator')<div class="mb-4"><a href="{{ route('anggaran.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Ajukan Iklan</a></div>@endif
    @if (auth()->user()->role === 'super_user')<div class="mb-4"><a href="{{ route('anggaran.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Tambah Pengeluaran Senior Manager</a></div>@endif
    <x-anggaran.period-filter :period="$period" :period-options="$periodOptions" />
    <x-dashboard.summary-cards :cards="$summaryCards" />
    <x-anggaran.limit-panel :limits="$limits" :can-manage-budget="$canManageBudget" :period="$period" :reference-options="$referenceOptions" />
    <x-anggaran.pending-panel :pending="$pending" />
    @if ($totalCount > $shownCount)
        <p class="mb-3 rounded-lg border border-tone-amber/40 bg-tone-amber/10 px-3 py-2 text-xs text-tone-amber">Menampilkan {{ number_format($shownCount, 0, ',', '.') }} dari {{ number_format($totalCount, 0, ',', '.') }} laporan periode ini. Persempit dengan filter periode lain bila perlu.</p>
    @endif
    <x-anggaran.grouped-table :groups="$groups" />
</x-layouts.app>
