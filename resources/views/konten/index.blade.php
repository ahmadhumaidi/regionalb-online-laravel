<x-layouts.app title="Monitoring Konten Kampus" active="konten">
    <x-konten.filter-bar :filters="$filters" :reference-options="$referenceOptions" />
    <x-dashboard.summary-cards :cards="$summaryCards" />
    <x-konten.regional-table :regionals="$regionals" />
    <x-konten.posts-table :posts="$posts" />
</x-layouts.app>
