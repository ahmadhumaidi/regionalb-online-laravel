<x-layouts.app title="Anggaran & Laporan Iklan" active="anggaran">
    <x-anggaran.period-filter :period="$period" :period-options="$periodOptions" />
    <x-dashboard.summary-cards :cards="$summaryCards" />
    <x-anggaran.limit-panel :limits="$limits" />
    <x-anggaran.pending-panel :pending="$pending" />
    <x-anggaran.grouped-table :groups="$groups" />
</x-layouts.app>
