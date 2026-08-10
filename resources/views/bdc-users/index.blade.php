<x-layouts.app title="BDC Marketing" active="bdc-users">
    @if(session('status'))<div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @php($collabHealth = $syncHealth['collab'] ?? [])
    @php($bdcHealth = $syncHealth['bdc'] ?? [])
    @php
        $collabOk = ($collabHealth['status'] ?? '') === 'OK' && empty($collabHealth['errors']);
        $bdcOk = ($bdcHealth['status'] ?? '') === 'OK';
        $statTones = ['blue-dark', 'blue', 'blue-light', 'red'];
    @endphp
    <section class="mb-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-ink/15 border-l-4 bg-surface-muted/70 p-4 shadow-sm" style="border-left-color: var(--color-tone-{{ $collabOk ? 'green' : 'red' }})">
            <span class="text-xs font-bold tracking-wide text-ink-muted uppercase">Collab</span>
            <strong class="mt-1 block text-lg font-bold" style="color: var(--color-tone-{{ $collabOk ? 'green' : 'red' }})">{{ $collabHealth['status'] ?? '-' }}</strong>
            <small class="text-xs text-ink-muted">
                {{ ($collabHealth['synced_at'] ?? '') !== '' ? $collabHealth['synced_at'] . ' WIB' : 'Belum sinkron' }}
                @if(!empty($collabHealth['errors'])) &middot; ada error source @endif
            </small>
        </div>
        <div class="rounded-2xl border border-ink/15 border-l-4 bg-surface-muted/70 p-4 shadow-sm" style="border-left-color: var(--color-tone-{{ $bdcOk ? 'green' : 'red' }})">
            <span class="text-xs font-bold tracking-wide text-ink-muted uppercase">BDC Marketing</span>
            <strong class="mt-1 block text-lg font-bold" style="color: var(--color-tone-{{ $bdcOk ? 'green' : 'red' }})">{{ $bdcHealth['status'] ?? '-' }}</strong>
            <small class="text-xs text-ink-muted">
                {{ ($bdcHealth['synced_at'] ?? '') !== '' ? $bdcHealth['synced_at'] . ' WIB' : 'Belum sinkron' }}
                @if(!empty($bdcHealth['rows_count'])) &middot; {{ number_format((int) $bdcHealth['rows_count'], 0, ',', '.') }} snapshot @endif
            </small>
        </div>
    </section>
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach([['Staff Terbaca',$totals['staff']],['Total Data',$totals['total']],['Closing',$totals['closing']],['FU Hari Ini',$totals['fu_hari_ini']]] as $i => [$label,$value])<div class="rounded-2xl border border-ink/15 border-l-4 bg-surface-muted/70 p-4 shadow-sm" style="border-left-color: var(--color-tone-{{ $statTones[$i % count($statTones)] }})"><p class="text-xs font-medium text-ink-muted">{{ $label }}</p><p class="mt-1 text-lg font-bold" style="color: var(--color-tone-{{ $statTones[$i % count($statTones)] }})">{{ number_format($value,0,',','.') }}</p></div>@endforeach</section>
    <section class="mt-5 overflow-x-auto rounded-2xl glass-card p-5"><div class="mb-3 flex items-center justify-between gap-3"><div><h2 class="text-base font-semibold text-ink">BDC Marketing Report Users</h2><p class="text-sm text-ink-muted">{{ $data['message'] ?? 'Source API P2K' }} · {{ $data['source_mode'] ?? 'cache' }} · {{ $data['fetched_at'] ?? '-' }}</p></div>@if(in_array(auth()->user()->role,['super_user','executive_director','director','senior','mentor'],true))<form method="POST" action="{{ route('bdc-users.refresh') }}">@csrf<button class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Segarkan</button></form>@endif</div><table class="w-full min-w-[1200px] text-left text-xs"><thead><tr class="border-b border-border text-ink-muted"><th class="py-2 pr-2">NIK</th><th class="py-2 pr-2">Nama</th><th class="py-2 pr-2">Kampus</th><th class="py-2 pr-2">Wilayah</th>@foreach(['total'=>'Total','data_baru'=>'Baru','cold'=>'Cold','warm'=>'Warm','hot'=>'Hot','closing'=>'Closing','wawancara'=>'Wawancara','belum_herreg'=>'Belum Herreg','herreg'=>'Herreg','fu_hari_ini'=>'FU Hari Ini'] as $key=>$label)<th class="py-2 pr-2">{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($rows as $row)<tr class="border-b border-border/60"><td class="py-2 pr-2">{{ $row['nik']??'-' }}</td><td class="py-2 pr-2 font-semibold">{{ $row['nama']??'-' }}</td><td class="py-2 pr-2">{{ $row['kampus']??'-' }}</td><td class="py-2 pr-2">{{ $row['wilayah']??'-' }}</td>@foreach(['total','data_baru','cold','warm','hot','closing','wawancara','belum_herreg','herreg','fu_hari_ini'] as $key)<td class="py-2 pr-2">{{ number_format((float)($row[$key]??0),0,',','.') }}</td>@endforeach</tr>@empty<tr><td colspan="14" class="py-8 text-center text-ink-muted">Data API belum terbaca.</td></tr>@endforelse</tbody></table></section>
</x-layouts.app>
