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

<section class="journey-arena relative overflow-hidden rounded-[2rem] border border-white/80 bg-white/80 text-slate-900 shadow-xl shadow-blue-950/5 backdrop-blur">
    <div class="journey-arena-grid pointer-events-none absolute inset-0"></div>
    <div class="pointer-events-none absolute -left-24 top-12 h-72 w-72 rounded-full bg-blue-300/20 blur-3xl"></div>
    <div class="pointer-events-none absolute -right-20 bottom-0 h-72 w-72 rounded-full bg-cyan-200/20 blur-3xl"></div>

    <div class="relative flex flex-col gap-4 border-b border-slate-200/80 px-5 py-5 sm:flex-row sm:items-center sm:justify-between md:px-7">
        <div>
            <div class="flex items-center gap-2 text-xs font-black uppercase tracking-[.2em] text-cyan-300"><span class="journey-live-dot"></span> Live Mission Map</div>
            <h2 class="mt-1 text-xl font-black sm:text-2xl">Perjalanan tim hari ini</h2>
            <p class="mt-1 text-sm text-slate-500">Klik pemain untuk melihat misi yang sudah ditaklukkan.</p>
        </div>
        <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50/80 px-4 py-3">
            <div><span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Progress tim</span><strong class="text-lg text-slate-900" x-text="teamProgress+'%'"></strong></div>
            <div class="h-9 w-px bg-slate-200"></div>
            <div class="w-32 sm:w-44"><div class="mb-1 flex justify-between text-[10px] text-slate-500"><span>Misi selesai</span><span x-text="completedMissions+'/'+totalMissions"></span></div><div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="journey-team-progress h-full rounded-full" :style="`width:${teamProgress}%`"></div></div></div>
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
                    <div class="journey-stage-node {{ $stage['step'] === 0 ? 'is-start' : ($stage['step'] === 9 ? 'is-finish' : '') }}">
                        <span class="journey-stage-icon">{{ $stage['icon'] }}</span>
                        @if($stage['step'] > 0)<span class="journey-stage-number">{{ $stage['step'] }}</span>@endif
                    </div>
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
</section>
