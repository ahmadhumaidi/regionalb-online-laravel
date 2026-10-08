<aside class="rounded-3xl border border-border bg-white p-5 shadow-sm">
    <div class="mb-4 flex items-center gap-2">
        <span class="grid h-9 w-9 place-items-center rounded-xl bg-amber-50 text-lg">👑</span>
        <div>
            <h2 class="font-black">Leaderboard Aktivitas</h2>
            <p class="text-xs text-ink-muted">Total KPI harian yang diselesaikan</p>
        </div>
    </div>

    <form method="GET" action="{{ route('staff-journey') }}" class="mb-4 grid grid-cols-2 gap-2">
        <input type="hidden" name="date" value="{{ $journeyDate }}">
        <label class="min-w-0"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-ink-muted">Dari</span><input type="date" name="leaderboard_from" value="{{ $leaderboardFrom }}" max="{{ $leaderboardTo }}" class="min-h-10 w-full min-w-0 rounded-xl border-border px-2 text-xs"></label>
        <label class="min-w-0"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-ink-muted">Sampai</span><input type="date" name="leaderboard_to" value="{{ $leaderboardTo }}" min="{{ $leaderboardFrom }}" max="{{ now()->toDateString() }}" class="min-h-10 w-full min-w-0 rounded-xl border-border px-2 text-xs"></label>
        <button type="submit" class="col-span-2 min-h-10 rounded-xl bg-slate-900 px-3 text-xs font-black text-white hover:bg-slate-700">Terapkan Periode</button>
    </form>

    <div class="space-y-2">
        <template x-for="(person,index) in leaderboard" :key="person.id">
            <button type="button" @click="openLeaderboardStaff(person)" class="flex w-full items-center gap-3 rounded-xl border border-border/60 p-2.5 text-left hover:bg-surface-muted">
                <span class="w-6 text-lg" x-text="['🥇','🥈','🥉'][index]||'#'+(index+1)"></span>
                <img :src="person.avatar" :alt="person.name" x-on:error="$event.target.onerror=null; $event.target.src=person.avatar_fallback" class="h-9 w-9 rounded-full object-cover">
                <div class="min-w-0 flex-1"><b class="block truncate text-sm" x-text="person.name"></b><span class="text-xs text-ink-muted" x-text="person.unit"></span></div>
                <span class="text-right"><b class="block text-sm text-emerald-600" x-text="person.activity_total"></b><small class="text-[10px] text-ink-muted">aktivitas</small></span>
            </button>
        </template>
        <p x-show="leaderboard.length===0" class="rounded-xl border border-dashed border-border p-4 text-center text-xs text-ink-muted">Belum ada aktivitas pada periode ini.</p>
    </div>
    <button x-cloak x-show="filteredLeaderboard.length > 5" type="button" @click="leaderboardExpanded = !leaderboardExpanded" class="mt-3 flex min-h-10 w-full items-center justify-center gap-2 rounded-xl border border-border bg-white px-3 text-xs font-black text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700">
        <span x-text="leaderboardExpanded ? 'Tampilkan lebih sedikit' : 'Tampilkan lainnya'"></span>
        <span aria-hidden="true" class="text-base leading-none" x-text="leaderboardExpanded ? '↑' : '↓'"></span>
    </button>
</aside>
