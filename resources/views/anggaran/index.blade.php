<x-layouts.app title="Anggaran & Laporan Iklan" active="anggaran">
    @if (auth()->user()->role === 'koordinator')<div class="mb-4"><a href="{{ route('anggaran.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Ajukan Iklan</a></div>@endif
    <x-anggaran.period-filter :period="$period" :period-options="$periodOptions" />
    <x-dashboard.summary-cards :cards="$summaryCards" />
    <x-anggaran.limit-panel :limits="$limits" />
    <x-anggaran.pending-panel :pending="$pending" />
    <x-anggaran.grouped-table :groups="$groups" />
</x-layouts.app>
