<x-layouts.app title="Kegiatan Marketing" active="kegiatan">
    <div class="mb-4"><a href="{{ route('aktivitas.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Tambah Aktivitas Lainnya</a></div>
    <x-reports.report-table :rows="$rows" title="Daftar Kegiatan Marketing" />
</x-layouts.app>
