<section class="rounded-3xl border border-border bg-white p-4 shadow-sm"><div class="mb-4 flex items-center gap-2.5"><span class="grid h-9 w-9 place-items-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="clipboard" class="h-4 w-4"/></span><div><h2 class="text-base font-black">Daftar Aktivitas KPI</h2><p class="text-xs text-ink-muted">Hanya kategori DAILY yang menentukan checkpoint dan FINISH.</p></div></div>
    <div class="space-y-4">
        @foreach(['DAILY'=>['Menentukan checkpoint & FINISH hari ini','bg-blue-100 text-blue-700'],'WEEKLY'=>['Target mingguan, tidak menahan FINISH harian','bg-violet-100 text-violet-700'],'PERIODIC'=>['Target periode, tidak menahan FINISH harian','bg-amber-100 text-amber-700']] as $category=>$meta)
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2"><span class="rounded-full px-2 py-0.5 text-[9px] font-black tracking-wider {{ $meta[1] }}">{{ $category }}</span><span class="text-[11px] text-ink-muted">{{ $meta[0] }}</span></div>
                <div class="grid gap-1.5 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach(collect($activities)->where('category',$category) as $activity)
                        @php
                            $iconTone = match ($activity['key']) {
                                'instagram' => 'text-pink-600',
                                'facebook' => 'text-blue-600',
                                'tiktok' => 'text-slate-950',
                                default => 'text-blue-600',
                            };
                        @endphp
                        <article class="group flex flex-col rounded-xl border border-border/60 bg-surface-muted/60 p-2 transition hover:-translate-y-0.5 hover:border-blue-300 hover:bg-blue-50 hover:shadow-md">
                            <button type="button" @click="selectedActivity={{ Illuminate\Support\Js::from($activity) }}" class="flex w-full items-center gap-2 text-left focus:outline-none">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white {{ $iconTone }} shadow-sm transition group-hover:bg-blue-600 group-hover:text-white"><x-icon :name="$activity['icon']" class="h-4 w-4"/></span>
                                <span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold">{{ $activity['title'] }}</span><span class="text-[10px] text-ink-muted">Target: {{ $activity['target'] }}</span></span>
                                <x-icon name="chevron-right" class="h-3.5 w-3.5 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-blue-600"/>
                            </button>
                            <div class="mt-2 flex items-center justify-between border-t border-slate-200/80 pt-1.5">
                                <button type="button" @click="selectedActivity={{ Illuminate\Support\Js::from($activity) }}" class="flex items-center gap-1 text-[9px] font-bold hover:text-blue-700"><span class="text-emerald-600" x-text="'Sudah '+activityCount(@js($activity['key']),true)"></span><span class="text-slate-300">·</span><span class="text-rose-600" x-text="'Belum '+activityCount(@js($activity['key']),false)"></span></button>
                                @if(!empty($activity['report_notice']))
                                    <button type="button" onclick="alert(@js($activity['report_notice']))" class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-2 py-1 text-[10px] font-bold text-white shadow-sm hover:bg-blue-700"><x-icon name="edit" class="h-3 w-3"/> Lapor</button>
                                @else
                                    <a href="{{ $activity['report_url'] }}" @if(!empty($activity['external'])) target="_blank" rel="noopener noreferrer" @endif class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-2 py-1 text-[10px] font-bold text-white shadow-sm hover:bg-blue-700"><x-icon name="edit" class="h-3 w-3"/> Lapor</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</section>

<div x-cloak x-show="selectedActivity" x-transition.opacity class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="selectedActivity=null" role="dialog" aria-modal="true" aria-label="Status aktivitas KPI">
    <section x-show="selectedActivity" x-transition class="flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-600"><x-icon name="clipboard" class="h-5 w-5"/></span><div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-[.18em] text-blue-600">Status KPI</p><h3 class="truncate text-lg font-black text-slate-900" x-text="selectedActivity?.title"></h3><p class="text-xs text-slate-500" x-text="'Target '+(selectedActivity?.target||'-')"></p></div></div>
            <button type="button" @click="selectedActivity=null" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 text-xl text-slate-500 hover:bg-slate-200" aria-label="Tutup">×</button>
        </header>

        <div class="grid min-h-0 flex-1 gap-4 overflow-y-auto bg-slate-50/70 p-4 sm:grid-cols-2 sm:p-6">
            <article class="rounded-2xl border border-emerald-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between"><div><h4 class="font-black text-emerald-700">Sudah mengerjakan</h4><p class="text-xs text-slate-500">Target aktivitas tercapai</p></div><span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700" x-text="activityPeople('done').length"></span></div>
                <div class="space-y-2"><template x-for="person in activityPeople('done')" :key="person.id"><button type="button" @click="selectedStaff=person; selectedActivity=null" class="flex w-full items-center gap-3 rounded-xl bg-emerald-50/70 p-2.5 text-left hover:bg-emerald-100"><img :src="person.avatar" :alt="person.name" x-on:error="$event.target.onerror=null; $event.target.src=person.avatar_fallback" class="h-9 w-9 rounded-full object-cover"><span class="min-w-0 flex-1"><b class="block truncate text-sm text-slate-900" x-text="person.name"></b><small class="block truncate text-slate-500" x-text="person.unit"></small></span><span class="text-emerald-600">✓</span></button></template><p x-show="activityPeople('done').length===0" class="rounded-xl border border-dashed border-slate-200 px-3 py-5 text-center text-sm text-slate-400">Belum ada staff.</p></div>
            </article>

            <article class="rounded-2xl border border-rose-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between"><div><h4 class="font-black text-rose-700">Belum mengerjakan</h4><p class="text-xs text-slate-500">Target belum tercapai</p></div><span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-black text-rose-700" x-text="activityPeople('pending').length"></span></div>
                <div class="space-y-2"><template x-for="person in activityPeople('pending')" :key="person.id"><button type="button" @click="selectedStaff=person; selectedActivity=null" class="flex w-full items-center gap-3 rounded-xl bg-rose-50/70 p-2.5 text-left hover:bg-rose-100"><img :src="person.avatar" :alt="person.name" x-on:error="$event.target.onerror=null; $event.target.src=person.avatar_fallback" class="h-9 w-9 rounded-full object-cover grayscale-[.2]"><span class="min-w-0 flex-1"><b class="block truncate text-sm text-slate-900" x-text="person.name"></b><small class="block truncate text-slate-500" x-text="person.unit"></small></span><span class="text-rose-500">○</span></button></template><p x-show="activityPeople('pending').length===0" class="rounded-xl border border-dashed border-slate-200 px-3 py-5 text-center text-sm text-slate-400">Tidak ada staff.</p></div>
            </article>

            <article x-show="activityPeople('untracked').length>0" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:col-span-2">
                <div class="mb-3 flex items-center justify-between"><div><h4 class="font-black text-amber-800">Data belum terhubung</h4><p class="text-xs text-amber-700">Status belum dapat dinilai dari sumber data saat ini</p></div><span class="rounded-full bg-amber-200 px-2.5 py-1 text-xs font-black text-amber-800" x-text="activityPeople('untracked').length"></span></div>
                <div class="flex flex-wrap gap-2"><template x-for="person in activityPeople('untracked')" :key="person.id"><span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-white px-2.5 py-1.5"><img :src="person.avatar" :alt="person.name" x-on:error="$event.target.onerror=null; $event.target.src=person.avatar_fallback" class="h-6 w-6 rounded-full object-cover"><b class="text-xs text-slate-700" x-text="person.name"></b></span></template></div>
            </article>
        </div>
    </section>
</div>
