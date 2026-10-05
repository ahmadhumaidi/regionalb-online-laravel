@php
    $journeyStages = [
        ['step' => 0, 'label' => 'Basecamp', 'hint' => 'Belum mulai', 'icon' => '🚩'],
        ['step' => 1, 'label' => 'Kick Off', 'hint' => 'CHECKPOINT 1', 'icon' => '⚡'],
        ['step' => 2, 'label' => 'On Fire', 'hint' => 'CHECKPOINT 2', 'icon' => '🔥'],
        ['step' => 3, 'label' => 'Momentum', 'hint' => 'CHECKPOINT 3', 'icon' => '🚀'],
        ['step' => 4, 'label' => 'Halfway', 'hint' => 'CHECKPOINT 4', 'icon' => '💪'],
        ['step' => 5, 'label' => 'Power Up', 'hint' => 'CHECKPOINT 5', 'icon' => '⚡'],
        ['step' => 6, 'label' => 'Final Push', 'hint' => 'CHECKPOINT 6', 'icon' => '🎯'],
        ['step' => 7, 'label' => 'One More', 'hint' => 'CHECKPOINT 7', 'icon' => '🔓'],
        ['step' => 8, 'label' => 'Almost There', 'hint' => 'CHECKPOINT 8', 'icon' => '⭐'],
        ['step' => 9, 'label' => 'Champion', 'hint' => 'CHECKPOINT 9 · Target Harian Tuntas', 'icon' => '🏆'],
    ];
@endphp

<style>
    .journey-arena:fullscreen { display: flex; height: 100vh; flex-direction: column; overflow: hidden; border-radius: 0; background: #fff; }
    .journey-arena:fullscreen .journey-map-scroll { flex: 1 1 auto; }
    .journey-arena::backdrop { background: #0f172a; }
    .journey-fallback-fullscreen { overflow: hidden; }
    .journey-fallback-fullscreen .journey-arena { position: fixed; inset: 0; z-index: 9999; display: flex; height: 100vh; flex-direction: column; overflow: hidden; border-radius: 0; background: #fff; }
    .journey-fallback-fullscreen .journey-map-scroll { flex: 1 1 auto; }
</style>
<section x-ref="journeyArena" class="journey-arena relative overflow-hidden rounded-[2rem] border border-white/80 bg-white/80 text-slate-900 shadow-xl shadow-blue-950/5 backdrop-blur">
    <div class="journey-arena-grid pointer-events-none absolute inset-0"></div>
    <div class="pointer-events-none absolute -left-24 top-12 h-72 w-72 rounded-full bg-blue-300/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -right-20 bottom-0 h-72 w-72 rounded-full bg-cyan-200/20 blur-3xl"></div>

    <div class="relative flex flex-col gap-4 border-b border-slate-200/80 px-5 py-5 sm:flex-row sm:items-center sm:justify-between md:px-7">
        <div>
            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-[.2em] text-cyan-300"><span class="journey-live-dot"></span> Live Mission Map</div>
            <h2 class="mt-1 text-xl font-black sm:text-2xl">Perjalanan tim hari ini</h2>
            <p class="mt-1 text-sm text-slate-500">Klik pemain untuk melihat misi yang sudah ditaklukkan.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
            <button type="button" @click="toggleJourneyFullscreen()" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-black text-slate-700 shadow-sm transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700" :title="isFullscreen ? 'Keluar dari layar penuh' : 'Tampilkan perjalanan tim dalam layar penuh'">
                <span class="text-lg leading-none" aria-hidden="true" x-text="isFullscreen ? '⊟' : '⛶'"></span>
                <span x-text="isFullscreen ? 'Keluar' : 'Layar Penuh'"></span>
            </button>
            <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
                <div><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Progress tim</span><strong class="text-lg text-slate-900" x-text="teamProgress+'%'"></strong></div>
                <div class="h-9 w-px bg-slate-200"></div>
                <div class="w-32 sm:w-44"><div class="mb-1 flex justify-between text-[10px] text-slate-500"><span>Misi selesai</span><span x-text="completedMissions+'/'+totalMissions"></span></div><div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="journey-team-progress h-full rounded-full" :style="`width:${teamProgress}%`"></div></div></div>
            </div>
        </div>
    </div>

    <div class="journey-map-scroll relative overflow-x-auto px-5 pb-7 pt-9 md:px-7">
        <div class="journey-map relative mx-auto grid min-w-[1240px] grid-cols-10 gap-5 pb-2">
            <svg class="journey-winding-route pointer-events-none absolute inset-x-[4%] top-2 h-32 w-[92%]" viewBox="0 0 1040 128" preserveAspectRatio="none" aria-hidden="true">
                <path d="M10 42 C75 42 62 94 132 94 S190 24 260 24 S326 96 390 96 S460 34 520 34 S584 94 650 94 S710 28 780 28 S842 94 910 94 S970 45 1030 45" fill="none" stroke="#cbd5e1" stroke-width="13" stroke-linecap="round"/>
                <path d="M10 42 C75 42 62 94 132 94 S190 24 260 24 S326 96 390 96 S460 34 520 34 S584 94 650 94 S710 28 780 28 S842 94 910 94 S970 45 1030 45" fill="none" stroke="#ffffff" stroke-width="3" stroke-dasharray="9 10" stroke-linecap="round"/>
            </svg>

            @foreach($journeyStages as $stage)
                <article class="journey-stage journey-stage-{{ $stage['step'] }} relative z-10 flex min-w-0 flex-col items-center text-center" :class="{{ $stage['step'] === 9 ? "at(9).length ? 'is-active' : ''" : "at({$stage['step']}).length ? 'is-active' : ''" }}">
                    <button type="button" @click="selectedCheckpoint={{ Illuminate\Support\Js::from($stage) }}" class="journey-stage-node cursor-pointer focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-300 {{ $stage['step'] === 0 ? 'is-start' : ($stage['step'] === 9 ? 'is-finish' : '') }}" title="Lihat staff di {{ $stage['label'] }}">
                        <span class="journey-stage-icon">{{ $stage['icon'] }}</span>
                        @if($stage['step'] > 0)<span class="journey-stage-number">{{ $stage['step'] }}</span>@endif
                    </button>
                    <strong class="mt-2.5 block text-xs font-black">{{ $stage['label'] }}</strong>
                    <span class="mt-0.5 text-[9px] font-semibold tracking-wide text-slate-500">{{ $stage['hint'] }}</span>

                    <div class="journey-player-bay mt-4 w-full rounded-2xl border border-slate-200 bg-white/90 px-2 py-3 shadow-sm">
                        <div class="mb-2 flex items-center justify-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <span class="h-1.5 w-1.5 rounded-full {{ $stage['step'] === 9 ? 'bg-amber-400' : 'bg-cyan-400' }}"></span>
                            <span x-text="{{ $stage['step'] === 9 ? 'at(9)' : "at({$stage['step']})" }}.length+' pemain'"></span>
                        </div>
                        @include('staff-journey.partials.staff-cluster', [
                            'expression' => $stage['step'] === 9 ? 'at(9)' : 'at('.$stage['step'].')',
                            'muted' => $stage['step'] === 0,
                        ])
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    <div class="relative flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-slate-200/80 bg-slate-50/70 px-5 py-3 text-[11px] font-semibold text-slate-500 md:px-7">
        <span class="flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-cyan-400"></i> Sedang berjuang</span>
        <span class="flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-amber-400"></i> Finish hari ini</span>
        <span class="ml-auto hidden text-slate-500 sm:block">Geser arena untuk melihat seluruh checkpoint →</span>
    </div>

    @include('staff-journey.partials.drawer', ['activities'=>$activities])

    <div x-cloak x-show="selectedCheckpoint" x-transition.opacity class="fixed inset-0 z-[80] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="selectedCheckpoint=null" role="dialog" aria-modal="true" aria-label="Staff pada checkpoint">
        <section x-show="selectedCheckpoint" x-transition class="flex max-h-[85vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
            <header class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-blue-50 text-xl" x-text="selectedCheckpoint?.icon"></span>
                    <div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-[.18em] text-blue-600">Posisi Pemain</p><h3 class="truncate text-lg font-black text-slate-900" x-text="selectedCheckpoint?.label"></h3><p class="text-xs text-slate-500" x-text="checkpointPeople().length+' staff pada checkpoint ini'"></p></div>
                </div>
                <button type="button" @click="selectedCheckpoint=null" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 text-xl text-slate-500 hover:bg-slate-200" aria-label="Tutup">×</button>
            </header>
            <div class="overflow-y-auto p-4 sm:p-5">
                <div class="space-y-2">
                    <template x-for="person in checkpointPeople()" :key="person.id">
                        <button type="button" @click="openStaffFromCheckpoint(person)" class="flex w-full items-center gap-3 rounded-2xl border border-slate-200 p-3 text-left transition hover:border-blue-300 hover:bg-blue-50/60">
                            <img :src="person.avatar" :alt="person.name" x-on:error="$event.target.onerror=null; $event.target.src=person.avatar_fallback" class="h-12 w-12 shrink-0 rounded-full object-cover shadow-sm">
                            <span class="min-w-0 flex-1"><b class="block truncate text-sm text-slate-900" x-text="person.name"></b><small class="block truncate text-slate-500" x-text="person.unit+' · '+person.regional"></small><span class="mt-1 block text-[11px] font-bold text-blue-600" x-text="person.completed_daily+' / '+person.total_daily+' aktivitas selesai'"></span></span>
                            <span class="text-xs font-bold text-slate-400">Profil →</span>
                        </button>
                    </template>
                    <p x-show="checkpointPeople().length===0" class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-400">Belum ada staff pada checkpoint ini.</p>
                </div>
            </div>
        </section>
    </div>
</section>
