<x-layouts.app title="Pencapaian Staff" active="pencapaian">
    <x-pencapaian.filter-bar :filters="$filters" :reference-options="$referenceOptions" />
    <x-dashboard.summary-cards :cards="$summaryCards" />
    <x-pencapaian.regional-summary :regional-summary="$regionalSummary" :sources="$sources" />
    <x-pencapaian.staff-table :rows-by-regional="$rowsByRegional" />
</x-layouts.app>
