<div class="mt-8 space-y-6 border-t border-slate-200 pt-6">
    <template x-for="track in periodTracks" :key="track.key">
        <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5" :aria-label="'Perjalanan '+track.label">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div><h3 class="text-lg font-black" x-text="'🏁 Finish '+track.label"></h3><p class="mt-1 max-w-xl text-xs text-slate-500" x-text="track.hint"></p>
                    <p class="mt-1 text-xs font-semibold text-slate-600">
                        @php($finishDate = \Carbon\CarbonImmutable::parse($journeyDate))
                        <span x-show="track.key==='weekly'">{{ $finishDate->startOfWeek()->translatedFormat('d M Y') }} – {{ $finishDate->translatedFormat('d M Y') }}</span>
                        <span x-show="track.key==='monthly'">Spanduk: {{ $finishDate->subMonthNoOverflow()->startOfMonth()->translatedFormat('d M Y') }} · Brosur: {{ $finishDate->subMonthsNoOverflow(2)->startOfMonth()->translatedFormat('d M Y') }} · Sampai {{ $finishDate->translatedFormat('d M Y') }}</span>
                    </p>
                </div>
                <span class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700" x-text="periodPeople(track,track.keys.length).length+' / '+filteredStaff.length+' staff finish'"></span>
            </div>
            <div class="mb-5 h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="periodProgress(track)" :aria-label="'Progres tim '+track.label"><div class="h-full rounded-full bg-emerald-500 transition-all duration-700" :style="`width:${periodProgress(track)}%`"></div></div>
            <div class="overflow-x-auto pb-2">
                <div class="relative grid gap-3" :style="`grid-template-columns:repeat(${track.steps.length}, minmax(140px,1fr));min-width:${track.steps.length*155}px`">
                    <div class="pointer-events-none absolute inset-x-12 top-6 border-t-4 border-dashed border-slate-300" aria-hidden="true"></div>
                    <template x-for="(label,step) in track.steps" :key="step">
                        <article class="relative text-center" x-data="{ expanded:false }">
                            <div class="relative mx-auto grid h-12 w-12 place-items-center rounded-full border-4 border-white text-lg font-black shadow-sm" :class="step===track.keys.length?'bg-amber-400 text-slate-900':'bg-slate-800 text-white'" x-text="step===track.keys.length?'🏁':(step===0?'🚩':step)"></div>
                            <strong class="mt-2 block text-xs font-black" x-text="label"></strong>
                            <div x-show="step===track.keys.length" class="mx-auto mt-2 h-3 w-20 rounded-sm" style="background:repeating-conic-gradient(#0f172a 0% 25%,white 0% 50%) 0 0 / 12px 12px" aria-hidden="true"></div>
                            <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="mb-2 text-[10px] font-bold text-slate-500" x-text="periodPeople(track,step).length+' pemain'"></p>
                                <div class="flex min-h-10 flex-wrap justify-center gap-1">
                                    <template x-for="person in periodPeople(track,step).slice(0,expanded?undefined:5)" :key="person.id">
                                        <button type="button" @click="selectedStaff=person" :title="person.name+' · '+periodCompleted(person,track)+' / '+track.keys.length+' misi '+track.label" class="rounded-full focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-300"><img :src="person.avatar" :alt="person.name" x-on:error="$event.target.onerror=null;$event.target.src=person.avatar_fallback" class="h-9 w-9 rounded-full border-2 border-white object-cover shadow-sm"></button>
                                    </template>
                                    <button type="button" x-show="!expanded&&periodPeople(track,step).length>5" @click="expanded=true" class="h-9 w-9 rounded-full bg-slate-200 text-xs font-bold" x-text="'+'+(periodPeople(track,step).length-5)"></button>
                                    <span x-show="periodPeople(track,step).length===0" class="text-sm text-slate-400">–</span>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </div>
        </section>
    </template>
</div>
