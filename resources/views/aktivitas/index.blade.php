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
    <section class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach([['Aktif',$summary['active'],'active','red'],['Menunggu Korwil',$summary['korwil'],'korwil','amber'],['Ke Senior Manager',$summary['senior'],'senior','purple'],['Selesai',$summary['done'],'done','green']] as [$label,$value,$workflow,$tone])
            <a href="{{ route('aktivitas', ['type'=>'kendala', 'workflow'=>$workflow]) }}" class="rounded-2xl border bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $filters['workflow'] === $workflow ? 'border-brand-500 ring-2 ring-brand-100' : 'border-border' }}"><span class="text-xs text-ink-muted">{{ $label }}</span><strong class="mt-1 block text-2xl text-tone-{{ $tone }}">{{ $value }}</strong></a>
        @endforeach
    </section>
    <nav class="mb-4 flex gap-2 overflow-x-auto rounded-2xl border border-border bg-white p-2">
        @foreach(['all'=>'Semua','kendala'=>'Kendala','other'=>'Aktivitas','marketing'=>'Marketing'] as $key=>$label)<a href="{{ route('aktivitas', array_merge(request()->except('type', 'workflow', 'action'), ['type'=>$key])) }}" class="whitespace-nowrap rounded-xl px-3 py-2 text-sm font-semibold {{ $filters['type']===$key && $filters['action'] !== '1' ? 'bg-brand-600 text-white' : 'text-ink-muted hover:bg-surface-muted' }}">{{ $label }} <span class="ml-1 opacity-75">{{ $counts[$key] }}</span></a>@endforeach
        <a href="{{ route('aktivitas', ['type'=>'all', 'action'=>1]) }}" class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl border px-3 py-2 text-sm font-bold transition {{ $filters['action'] === '1' ? 'border-amber-600 bg-amber-500 text-slate-950 shadow-sm' : 'border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-300 hover:bg-amber-100' }}">
            Perlu tindakan
            @if($actionRequiredCount > 0)<span class="grid min-w-5 place-items-center rounded-full bg-amber-700 px-1.5 py-0.5 text-[10px] font-black text-white ring-2 ring-amber-200">{{ $actionRequiredCount > 99 ? '99+' : $actionRequiredCount }}</span>@endif
        </a>
    </nav>
    <form method="GET" class="mb-4 grid gap-2 rounded-2xl border border-border bg-white p-3 sm:grid-cols-2 lg:grid-cols-6">
        <input type="hidden" name="type" value="{{ $filters['type'] }}"><input type="hidden" name="workflow" value="{{ $filters['workflow'] }}"><input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-lg border-border"><input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-lg border-border"><input name="staff" value="{{ $filters['staff'] }}" placeholder="Cari staff" class="rounded-lg border-border"><select name="status" class="rounded-lg border-border"><option value="">Semua status</option>@foreach(['Dikirim','Ditindak Lanjuti','Disetujui','Selesai','Revisi'] as $status)<option @selected($filters['status']===$status)>{{ $status }}</option>@endforeach</select><label class="flex items-center gap-2 rounded-lg border border-border px-3 text-sm"><input type="checkbox" name="action" value="1" @checked($filters['action']==='1')> Perlu tindakan</label><div class="flex gap-2"><button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('aktivitas') }}" class="rounded-lg border border-border px-3 py-2 text-sm">Reset</a></div>
    </form>
    <x-reports.report-table :rows="$rows" title="Daftar Laporan" />
</x-layouts.app>
