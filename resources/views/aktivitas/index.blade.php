<x-layouts.app title="Aktivitas Lain" active="aktivitas">
    <div class="mb-4"><a href="{{ route('aktivitas.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Tambah Aktivitas</a></div>
    <x-reports.report-table :rows="$rows" title="Daftar Aktivitas Lain" />
</x-layouts.app>
