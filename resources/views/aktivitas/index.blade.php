<x-layouts.app title="Laporan" active="aktivitas">
    <div class="mb-4 flex flex-wrap gap-2">
        @if(auth()->user()->role === \App\Models\RsmUser::ROLE_STAFF)
            <a href="{{ route('aktivitas.create', ['kendala' => 1]) }}" class="inline-flex items-center gap-2 rounded-lg border-2 border-red-200 bg-red-700 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-red-900/25 transition hover:border-white hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-300">
                <span aria-hidden="true" class="text-base leading-none">⚠</span>
                Laporkan Kendala
            </a>
        @endif
        <a href="{{ route('aktivitas.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Tambah Aktivitas Lain</a>
        <a href="{{ route('kegiatan.create') }}" class="rounded-lg border border-brand-600 bg-white px-4 py-2 text-sm font-semibold text-brand-700">Tambah Kegiatan Marketing</a>
        <a href="{{ route('upload-konten-sosmed') }}" class="rounded-lg border border-brand-600 bg-white px-4 py-2 text-sm font-semibold text-brand-700">Upload Konten</a>
    </div>
    <x-reports.report-table :rows="$rows" title="Daftar Laporan" />
</x-layouts.app>
