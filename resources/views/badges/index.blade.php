<x-layouts.app title="League Season & Badge" active="badges">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @php
        $nextThreshold = $nextLeague['threshold'] ?? $leagues[array_key_last($leagues)]['threshold'];
        $seasonProgress = $nextLeague ? min(100, (int) round(($seasonXp / $nextThreshold) * 100)) : 100;
        $seasonEndsAt = $season['end']->copy()->setTimezone('Asia/Jakarta')->toIso8601String();
    @endphp

    <section class="overflow-hidden rounded-3xl text-white shadow-xl" style="background: radial-gradient(circle at 88% 12%, rgba(96, 165, 250, .28), transparent 32%), linear-gradient(135deg, #101a3d 0%, #1e3a73 52%, #31245f 100%);">
        <div class="grid gap-6 p-5 sm:p-8 lg:grid-cols-[1.25fr_.75fr] lg:items-center">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-bold tracking-wider text-sky-200 uppercase">
                    <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-300"></span>{{ $season['label'] }}
                </div>
                <h2 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">Kejar puncak league-mu.</h2>
                <p class="mt-3 max-w-xl text-sm leading-6 text-indigo-100">League dihitung dari XP yang kamu kumpulkan pada season ini. Reset setiap tiga bulan membuat semua orang memulai kesempatan baru, sementara XP lifetime dan level tetap aman.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="#tier-league" class="rounded-xl px-4 py-2.5 text-sm font-bold shadow-lg transition" style="background: #ffffff; color: #172554;">Lihat target league</a>
                    <a href="#badge-koleksi" class="rounded-xl border border-white/30 px-4 py-2.5 text-sm font-bold text-white transition" style="background: rgba(15, 23, 42, .22);">Jelajahi badge</a>
                </div>
            </div>
            <div class="rounded-3xl border border-white/20 p-5 sm:p-6" style="background: rgba(5, 12, 35, .48);">
                <div class="flex items-center gap-4">
                    <div class="relative h-20 w-20 shrink-0">
                        <img src="{{ asset('images/league/'.strtolower($league).'.png') }}" alt="League {{ $league }}" class="h-full w-full object-contain drop-shadow-lg">
                    </div>
                    <div>
                        <p class="text-xs font-semibold tracking-widest text-indigo-200 uppercase">League saat ini</p>
                        <p class="mt-1 text-3xl font-black">{{ $league }}</p>
                        <p class="mt-1 text-xs text-indigo-200">{{ number_format($seasonXp, 0, ',', '.') }} XP season</p>
                    </div>
                </div>
                <div class="mt-6 flex items-end justify-between gap-3 text-xs">
                    <span class="font-semibold text-indigo-100">
                        @if ($nextLeague)
                            Menuju {{ $nextLeague['name'] }}
                        @else
                            Tier tertinggi tercapai
                        @endif
                    </span>
                    <strong>{{ $seasonProgress }}%</strong>
                </div>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-white/15"><div class="h-full rounded-full bg-gradient-to-r from-sky-300 to-emerald-300" style="width: {{ $seasonProgress }}%"></div></div>
                <p class="mt-3 text-xs text-indigo-200">Season berakhir dalam <strong class="text-white" data-countdown-target="{{ $seasonEndsAt }}">--:--:--</strong></p>
            </div>
        </div>
    </section>

    <section class="mt-5 grid gap-4 sm:grid-cols-3">
        <article class="rounded-2xl glass-card p-5"><p class="text-xs font-bold tracking-wider text-ink-muted uppercase">XP season</p><p class="mt-2 text-3xl font-black text-ink">{{ number_format($seasonXp, 0, ',', '.') }}</p><p class="mt-2 text-xs text-ink-muted">Dihitung sejak {{ $season['start']->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d M Y') }}</p></article>
        <article class="rounded-2xl glass-card p-5"><p class="text-xs font-bold tracking-wider text-ink-muted uppercase">XP lifetime</p><p class="mt-2 text-3xl font-black text-ink">{{ number_format($user->gamificationTransactions()->sum('xp'), 0, ',', '.') }}</p><p class="mt-2 text-xs text-ink-muted">Tidak pernah direset</p></article>
        <article class="rounded-2xl glass-card p-5"><p class="text-xs font-bold tracking-wider text-ink-muted uppercase">Badge terbuka</p><p class="mt-2 text-3xl font-black text-ink">{{ collect($earnedBadgeNames)->reject(fn ($name) => $name === $fallback['name'])->count() }}<span class="text-lg text-ink-muted">/{{ count($badges) }}</span></p><p class="mt-2 text-xs text-ink-muted">Pencapaian tetap tersimpan</p></article>
    </section>

    <section id="tier-league" class="mt-5 rounded-3xl glass-card p-5 sm:p-7">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-bold tracking-widest text-brand-700 uppercase">Jalur kemajuan</p><h2 class="mt-1 text-2xl font-black text-ink">Tier League</h2><p class="mt-2 text-sm text-ink-muted">Naikkan frame profile dan posisi tampilmu di Arena Performa dengan XP season.</p></div><span class="rounded-full bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700">Reset per kuartal</span></div>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($leagues as $tier)
                @php $isCurrent = $tier['name'] === $league; $isReached = $seasonXp >= $tier['threshold']; @endphp
                <article @class(['relative overflow-hidden rounded-2xl border p-4 text-center transition', 'border-brand-400 bg-brand-50/70 shadow-md' => $isCurrent, 'border-border bg-surface-muted/50' => ! $isCurrent])>
                    @if($isCurrent)<span class="absolute top-2 right-2 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">KAMU</span>@endif
                    <img src="{{ asset('images/league/'.strtolower($tier['name']).'.png') }}" alt="League {{ $tier['name'] }}" class="mx-auto h-20 w-20 object-contain {{ $isReached ? '' : 'grayscale opacity-35' }}">
                    <h3 class="mt-2 text-sm font-black text-ink">{{ $tier['name'] }}</h3><p class="mt-1 text-lg font-black text-brand-700">{{ number_format($tier['threshold'], 0, ',', '.') }}</p><p class="text-[11px] font-semibold text-ink-muted">XP season</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-5 grid gap-4 lg:grid-cols-3">
        @foreach ([['01', 'Kumpulkan XP', 'XP diperoleh dari aktivitas dan misi yang tervalidasi.'], ['02', 'Naikkan tier', 'Capai target XP season untuk membuka tier league berikutnya.'], ['03', 'Mulai season baru', 'Pada awal kuartal berikutnya, XP season kembali dihitung dari nol.']] as [$number, $title, $copy])
            <article class="rounded-2xl border border-border bg-surface-muted/50 p-5"><span class="text-3xl font-black text-brand-200">{{ $number }}</span><h3 class="mt-3 font-bold text-ink">{{ $title }}</h3><p class="mt-1 text-sm leading-6 text-ink-muted">{{ $copy }}</p></article>
        @endforeach
    </section>

    <section id="badge-koleksi" class="mt-5 rounded-3xl glass-card p-5 sm:p-7">
        <div class="flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs font-bold tracking-widest text-brand-700 uppercase">Koleksi permanen</p><h2 class="mt-1 text-2xl font-black text-ink">Badge</h2><p class="mt-2 text-sm text-ink-muted">Badge merekam pencapaianmu dan tidak ikut direset saat season league berganti.</p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-ink-muted">{{ count($badges) }} badge</span></div>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($badges as $badge)
                @php $earned = in_array($badge['name'], $earnedBadgeNames, true); @endphp
                <article class="rounded-2xl border border-border bg-surface-muted/40 p-4" @if($earned) style="border-color: var(--color-tone-{{ $badge['tone'] }}); background: color-mix(in srgb, var(--color-tone-{{ $badge['tone'] }}) 6%, transparent)" @endif>
                    <div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $earned ? '' : 'bg-slate-200 text-slate-400' }}" @if($earned) style="background: color-mix(in srgb, var(--color-tone-{{ $badge['tone'] }}) 15%, transparent); color: var(--color-tone-{{ $badge['tone'] }})" @endif><x-icon name="{{ $earned ? 'trophy' : 'lock' }}" class="h-5 w-5" /></span><div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-2"><h3 class="font-bold text-ink">{{ $badge['name'] }}</h3><span class="text-[10px] font-bold uppercase {{ $earned ? '' : 'text-ink-muted' }}" @if($earned) style="color: var(--color-tone-{{ $badge['tone'] }})" @endif>{{ $earned ? 'Terbuka' : 'Terkunci' }}</span></div><p class="mt-1 text-xs leading-5 text-ink-muted">{{ $badge['condition'] }}</p></div></div>
                </article>
            @endforeach
        </div>
    </section>

    @if ($canManageBadges)
        <details class="mt-5 rounded-3xl border border-amber-200 bg-amber-50/50 p-5">
            <summary class="cursor-pointer list-none font-bold text-amber-950"><span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-200 text-xs">⚙</span>Pengaturan admin badge <span class="ml-2 text-xs font-normal text-amber-800">Klik untuk membuka</span></summary>
            <form method="POST" action="{{ route('badges.update') }}" class="mt-5">@csrf
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($badges as $badge)
                        <article class="rounded-xl border border-amber-200 bg-white p-4"><h3 class="font-bold text-ink">{{ $badge['name'] }}</h3><label class="mt-3 block text-xs font-semibold text-ink-muted">Indikator<select name="settings[{{ $badge['key'] }}][indicator_key]" class="mt-1 w-full rounded-lg border-border bg-surface-muted text-sm">@foreach ($indicators as $indicatorKey => $indicator)<option value="{{ $indicatorKey }}" @selected(old('settings.'.$badge['key'].'.indicator_key', $badge['indicator_key']) === $indicatorKey)>{{ $indicator['label'] }}</option>@endforeach</select></label><label class="mt-3 block text-xs font-semibold text-ink-muted">Target<input type="number" min="0" step="{{ $indicators[$badge['indicator_key']]['step'] ?? '1' }}" name="settings[{{ $badge['key'] }}][target_value]" value="{{ old('settings.'.$badge['key'].'.target_value', (float) $badge['target_value']) }}" class="mt-1 w-full rounded-lg border-border bg-surface-muted"></label></article>
                    @endforeach
                </div>
                <div class="mt-4 flex justify-end"><button class="rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-600">Simpan ketentuan badge</button></div>
            </form>
        </details>
    @endif
</x-layouts.app>
