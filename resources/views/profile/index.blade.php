<x-layouts.app title="Profil Saya" active="profile">
    <div class="profile-glass-shell -mx-2 rounded-2xl bg-[#101227] p-3 text-white shadow-2xl sm:mx-0 sm:rounded-3xl sm:p-6">
        <div class="grid gap-5 lg:grid-cols-[270px_1fr]">
            <aside class="rounded-2xl border border-white/10 bg-[#212446] p-4 text-center sm:p-5">
                <div class="relative mx-auto h-28 w-28 sm:h-40 sm:w-40">
                    <x-league-photo :league="$league" :user="$user" text-size="text-lg" />
                </div>
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-3 text-left">
                    @csrf
                    <label for="profile_photo" class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-indigo-200/40 bg-white/5 px-3 py-2 text-xs font-semibold text-indigo-100 transition hover:border-sky-300 hover:bg-white/10 hover:text-white">
                        <x-icon name="photo" class="h-4 w-4" />
                        Ganti foto profil
                    </label>
                    <input id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="this.form.submit()">
                    <p class="mt-2 text-center text-[11px] text-indigo-300">JPG, PNG, atau WebP · maksimal 2 MB</p>
                    @error('profile_photo')
                        <p class="mt-2 rounded-lg bg-rose-400/15 px-2 py-1.5 text-center text-[11px] text-rose-200">{{ $message }}</p>
                    @enderror
                </form>
                <h2 class="mt-4 text-lg font-bold">{{ $user->name }}</h2>
                <p class="text-xs text-indigo-200">{{ $user->username }}</p>
                <div class="mt-3 flex flex-wrap justify-center gap-x-2 text-xs font-semibold text-indigo-100"><span>{{ $user->jabatan ?: \App\Support\RsmRole::label($user->role) }}</span><span>•</span><span>{{ $user->regional ?: 'Wilayah belum diatur' }}</span></div>
                <div class="mt-5 rounded-2xl bg-[#090b1e]/60 p-4 text-left"><div class="flex justify-between text-xs text-indigo-200"><span>Level {{ $level }}</span><strong class="text-white">{{ number_format($xp,0,',','.') }} XP</strong></div><div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-sky-400 progress-fill" style="width:{{ $levelProgress }}%"></div></div>@php $nextLeague = \App\Services\Dashboard\GamificationService::nextLeagueThreshold($seasonXp); @endphp<p class="mt-2 text-xs text-indigo-200">League {{ $league }} · {{ $leagueSeason['label'] }}</p><p class="mt-1 text-[11px] text-indigo-300">@if ($nextLeague){{ number_format($seasonXp,0,',','.') }} / {{ number_format($nextLeague['threshold'],0,',','.') }} XP season menuju League {{ $nextLeague['name'] }}@else League tertinggi season ini tercapai @endif</p><p class="mt-1 text-[10px] text-indigo-400">League direset setiap 3 bulan. XP dan level tetap permanen.</p></div>
                <nav class="mt-5 flex gap-1 overflow-x-auto pb-1 text-left text-xs font-semibold text-indigo-100 lg:grid lg:pb-0 lg:text-sm">@if($user->role === 'staff')<a href="#aktivitas-hari-ini" class="shrink-0 rounded-xl bg-black/25 px-3 py-2">Aktivitas Hari Ini</a>@endif<a href="#ringkasan" class="shrink-0 rounded-xl px-3 py-2 hover:bg-black/25">Ringkasan</a><a href="#daily-mission" class="shrink-0 rounded-xl px-3 py-2 hover:bg-black/25">Daily Mission</a><a href="#pencapaian" class="shrink-0 rounded-xl px-3 py-2 hover:bg-black/25">Pencapaian</a><a href="#aktivitas" class="shrink-0 rounded-xl px-3 py-2 hover:bg-black/25">Aktivitas</a></nav>
            </aside>
            <div class="space-y-5">
                @php
                    $leagueTiers = ['Starter', 'Silver', 'Gold', 'Platinum', 'Diamond'];
                    $leagueGridCols = implode(' ', array_map(fn ($t) => strtolower($t) === strtolower($league) ? '1.4fr' : '1fr', $leagueTiers));
                @endphp
                @if ($user->role === 'staff')
                    @php
                        $missionByKey = collect($dailyMissions)->keyBy(fn ($mission) => preg_replace('/_\d+$/', '', $mission['key']));
                        $activityCatalog = [
                            ['icon' => 'photo', 'title' => 'Update Konten Instagram & Facebook', 'target' => '1 konten', 'period' => 'Per hari', 'group' => 'Harian', 'url' => route('upload-konten-sosmed'), 'action' => 'Upload konten'],
                            ['icon' => 'photo', 'title' => 'Update Konten TikTok', 'target' => '1 konten', 'period' => 'Per hari', 'group' => 'Harian', 'url' => route('upload-konten-sosmed'), 'action' => 'Upload konten'],
                            ['icon' => 'bolt', 'title' => 'Live Streaming Media Sosial – Day', 'target' => '1 jam', 'period' => 'Per hari', 'group' => 'Harian', 'url' => route('aktivitas.create'), 'action' => 'Buat laporan'],
                            ['icon' => 'bolt', 'title' => 'Live Streaming Media Sosial – Night', 'target' => '1 jam', 'period' => 'Per minggu', 'group' => 'Mingguan', 'url' => route('aktivitas.create'), 'action' => 'Buat laporan'],
                            ['icon' => 'photo', 'title' => 'Update Story Instagram', 'target' => '1 story', 'period' => 'Per hari', 'group' => 'Harian', 'url' => route('upload-konten-sosmed'), 'action' => 'Upload story'],
                            ['icon' => 'chat', 'title' => 'Sapa Grup Affiliate', 'target' => '1 kali', 'period' => 'Per minggu', 'group' => 'Mingguan', 'url' => route('aktivitas.create'), 'action' => 'Buat laporan'],
                            ['icon' => 'users', 'title' => 'Canvassing', 'target' => '3 kali', 'period' => 'Per minggu', 'group' => 'Mingguan', 'url' => route('aktivitas.create'), 'action' => 'Buat laporan'],
                            ['icon' => 'document', 'title' => 'Sebar Brosur', 'target' => '200 lembar', 'period' => 'Per minggu', 'group' => 'Mingguan', 'url' => route('aktivitas.create'), 'action' => 'Buat laporan'],
                            ['icon' => 'flag', 'title' => 'Spanduk Kerja Sama', 'target' => '6 pcs', 'period' => 'Per 2 bulan', 'group' => 'Dua Bulanan', 'url' => route('aktivitas.create'), 'action' => 'Buat laporan'],
                            ['icon' => 'calendar', 'title' => 'Absen Masuk', 'target' => '100% on time', 'period' => 'Setiap hari kerja', 'group' => 'Harian', 'url' => route('aktivitas.create'), 'action' => 'Catat aktivitas'],
                            ['icon' => 'cloud', 'title' => 'Share Konten Facebook', 'target' => '5 share', 'period' => 'Per hari', 'group' => 'Harian', 'url' => route('aktivitas.create'), 'action' => 'Catat aktivitas', 'mission' => 'share_fb'],
                            ['icon' => 'chat', 'title' => 'Follow Up BDC', 'target' => '30 FU', 'period' => 'Per hari', 'group' => 'Harian', 'url' => route('crm'), 'action' => 'Buka CRM', 'mission' => 'fu'],
                            ['icon' => 'clipboard', 'title' => 'Laporan Aktivitas Lainnya', 'target' => '1 laporan', 'period' => 'Sesuai aktivitas', 'group' => 'Lainnya', 'url' => route('aktivitas.create'), 'action' => 'Buat laporan', 'mission' => 'aktivitas_lain'],
                        ];
                        $groupClasses = [
                            'Harian' => 'bg-sky-300/10 text-sky-200',
                            'Mingguan' => 'bg-violet-300/10 text-violet-200',
                            'Dua Bulanan' => 'bg-amber-300/10 text-amber-200',
                            'Lainnya' => 'bg-slate-300/10 text-slate-200',
                        ];
                    @endphp
                    <section id="aktivitas-hari-ini" class="relative overflow-hidden rounded-3xl border border-white/20 p-4 sm:p-6">
                        <div class="pointer-events-none absolute -right-16 -top-20 h-52 w-52 rounded-full bg-sky-400/20 blur-3xl"></div>
                        <div class="pointer-events-none absolute -bottom-20 left-1/3 h-44 w-44 rounded-full bg-amber-300/15 blur-3xl"></div>
                        <div class="relative">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                                <div>
                                    <span class="inline-flex items-center gap-2 rounded-full border border-sky-300/25 bg-sky-300/10 px-3 py-1 text-[11px] font-bold tracking-[0.16em] text-sky-200 uppercase"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300 shadow-[0_0_10px_rgba(110,231,183,.9)]"></span>Aktivitas Wajib Staff Unit</span>
                                    <h1 class="mt-3 text-2xl font-black tracking-tight text-white sm:text-3xl">Selamat datang, {{ Illuminate\Support\Str::before($user->name, ' ') }}!</h1>
                                    <p class="mt-2 max-w-2xl text-sm leading-6 text-indigo-100">Gunakan daftar ini sebagai panduan kerja {{ $user->campus_name ?: 'unitmu' }}. Prioritaskan target harian, lalu lanjutkan progres mingguan dan dua bulanan.</p>
                                </div>
                                <div class="min-w-[220px] rounded-2xl border border-white/10 bg-black/20 p-3.5">
                                    <div class="flex items-center justify-between text-xs"><span class="font-semibold text-indigo-200">Standar aktivitas</span><strong class="text-white">{{ count($activityCatalog) }} aktivitas</strong></div>
                                    <div class="mt-3 flex flex-wrap gap-1.5"><span class="rounded-full bg-sky-300/10 px-2 py-1 text-[10px] font-bold text-sky-200">7 harian</span><span class="rounded-full bg-violet-300/10 px-2 py-1 text-[10px] font-bold text-violet-200">4 mingguan</span><span class="rounded-full bg-amber-300/10 px-2 py-1 text-[10px] font-bold text-amber-200">1 dua bulanan</span></div>
                                    <p class="mt-2 text-[11px] leading-4 text-indigo-300">Aktivitas dengan data terintegrasi akan menampilkan progres otomatis.</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($activityCatalog as $index => $activity)
                                    @php
                                        $mission = isset($activity['mission']) ? $missionByKey->get($activity['mission']) : null;
                                        $isComplete = $mission && ($mission['done'] || $mission['claimed']);
                                        $groupClass = $groupClasses[$activity['group']] ?? $groupClasses['Lainnya'];
                                    @endphp
                                    <article class="group flex min-h-[190px] flex-col rounded-2xl border p-4 transition {{ $isComplete ? 'border-emerald-300/25 bg-emerald-300/[0.07]' : 'border-white/10 bg-[#111831]/80 hover:-translate-y-1 hover:border-sky-300/35' }}">
                                        <div class="flex items-start justify-between gap-3">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-xl {{ $isComplete ? 'bg-emerald-300/15 text-emerald-200' : 'bg-sky-300/10 text-sky-200' }}"><x-icon :name="$isComplete ? 'check' : $activity['icon']" class="h-5 w-5" /></span>
                                            <span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $isComplete ? 'bg-emerald-300/15 text-emerald-200' : $groupClass }}">{{ $isComplete ? 'SELESAI' : $activity['group'] }}</span>
                                        </div>
                                        <h3 class="mt-4 text-sm font-bold leading-5 text-white">{{ $activity['title'] }}</h3>
                                        <div class="mt-2 flex flex-1 items-start gap-2"><strong class="text-lg text-white">{{ $activity['target'] }}</strong><span class="mt-1 rounded-md bg-white/[0.06] px-2 py-0.5 text-[10px] font-semibold text-indigo-200">{{ $activity['period'] }}</span></div>
                                        @if ($mission)<div class="mt-2"><div class="flex items-center justify-between text-[10px] font-semibold text-indigo-300"><span>Progres otomatis</span><span>{{ number_format(min($mission['actual'], $mission['target']), 0, ',', '.') }}/{{ number_format($mission['target'], 0, ',', '.') }}</span></div><div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full {{ $isComplete ? 'bg-emerald-300' : 'bg-sky-300' }}" style="width: {{ $mission['progress'] }}%"></div></div></div>@endif
                                        <a href="{{ $activity['url'] }}" class="mt-3 inline-flex items-center justify-between rounded-xl border border-white/10 bg-white/[0.06] px-3 py-2 text-xs font-bold text-white transition hover:border-sky-300/30 hover:bg-sky-300/10"><span>{{ $isComplete ? 'Lihat detail' : $activity['action'] }}</span><x-icon name="chevron-right" class="h-3.5 w-3.5" /></a>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif
                <section id="ringkasan" class="grid grid-cols-2 gap-3 sm:grid-cols-3"><article class="rounded-2xl border border-white/10 bg-[#35385f] p-3 sm:p-4"><span class="text-xs text-indigo-200">League</span><strong class="mt-2 block text-xl">{{ $league }}</strong><div class="mt-2 grid items-end gap-1" style="grid-template-columns: {{ $leagueGridCols }}">@foreach ($leagueTiers as $tier)<img src="{{ asset('images/league/'.strtolower($tier).'.png') }}" alt="League {{ $tier }}" title="{{ $tier }}" class="{{ strtolower($league) === strtolower($tier) ? 'drop-shadow-[0_0_4px_rgba(250,204,21,0.7)]' : 'opacity-30 grayscale' }} aspect-square w-full transition-all">@endforeach</div><small class="mt-2 block text-indigo-200">XP dan konsistensi</small></article><article class="rounded-2xl border border-white/10 bg-[#35385f] p-3 sm:p-4"><span class="text-xs text-indigo-200">Badge</span><strong class="mt-2 block text-xl">{{ collect($badges)->where('ok', true)->count() }}/{{ count($badges) }}</strong><div class="mt-3 grid grid-cols-4 gap-1.5 sm:grid-cols-7">@foreach ($badges as $badge)<span class="group relative flex aspect-square w-full items-center justify-center rounded-full" style="background: {{ $badge['ok'] ? 'color-mix(in srgb, var(--color-tone-'.$badge['tone'].') 25%, transparent)' : 'rgba(255,255,255,.06)' }}; color: {{ $badge['ok'] ? 'var(--color-tone-'.$badge['tone'].')' : 'rgba(199,210,254,.45)' }}"><x-icon name="{{ $badge['icon'] }}" class="h-1/2 w-1/2 {{ $badge['ok'] ? '' : 'opacity-70 grayscale' }}" />@unless($badge['ok'])<span class="absolute -right-0.5 -top-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full border border-[#35385f] bg-[#101227] text-indigo-300"><x-icon name="lock" class="h-2 w-2" /></span>@endunless<span class="pointer-events-none absolute -top-8 left-1/2 z-10 -translate-x-1/2 rounded-md bg-black/90 px-2 py-1 text-[10px] font-semibold whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">{{ $badge['name'] }}</span></span>@endforeach</div></article><article class="col-span-2 rounded-2xl border border-white/10 bg-[#35385f] p-3 sm:col-span-1 sm:p-4"><span class="text-xs text-indigo-200">Aura</span><strong class="mt-2 block text-xl">{{ $score }}/100</strong><small class="text-indigo-200">Skor performa</small></article></section>
                <section id="daily-mission" class="rounded-2xl border border-white/10 bg-gradient-to-b from-[#1c2b52] to-[#111a33] p-3 sm:p-5">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-base font-black tracking-wide uppercase">Daily Mission</h2>
                            <p class="mt-1 text-xs text-indigo-200">Reset dalam <span class="font-semibold text-white" data-countdown-target="{{ $missionResetAt }}">--:--:--</span></p>
                        </div>
                        <div class="flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5">
                            <x-icon name="bolt" class="h-4 w-4 text-amber-300" />
                            <strong class="text-sm">{{ number_format($todayEnergy,0,',','.') }}</strong>
                        </div>
                    </div>

                    <div class="relative mb-6 px-2">
                        <div class="absolute top-4 right-6 left-6 h-1 rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-gradient-to-r from-amber-300 to-emerald-300 progress-fill" style="width: {{ min(100, ($todayEnergy / max($dailyChestTiers)) * 100) }}%"></div>
                        </div>
                        <div class="relative flex justify-between">
                            @foreach($dailyChestTiers as $tier)
                                @php $reached = $todayEnergy >= $tier; @endphp
                                <div class="flex flex-col items-center gap-1">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 {{ $reached ? 'border-emerald-300 bg-emerald-400/20 text-emerald-200' : 'border-white/20 bg-[#212446] text-indigo-300' }}">
                                        @if($reached)<x-icon name="check" class="h-4 w-4" />@else<x-icon name="chest" class="h-4 w-4" />@endif
                                    </span>
                                    <span class="text-[11px] font-semibold {{ $reached ? 'text-emerald-200' : 'text-indigo-300' }}">{{ $tier }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2">
                        @foreach($dailyMissions as $mission)
                            <div class="flex flex-wrap items-center gap-3 rounded-xl border {{ $mission['claimed'] ? 'border-emerald-300/30 bg-emerald-400/5' : 'border-white/10 bg-[#212446]' }} p-3 sm:flex-nowrap">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold tracking-wide uppercase">{{ $mission['label'] }}@if($mission['tier'])<span class="ml-1.5 text-[10px] font-semibold text-indigo-300">({{ $mission['tier'] }})</span>@endif</p>
                                    @unless($mission['done'])
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-sky-300 progress-fill" style="width: {{ $mission['progress'] }}%"></div></div>
                                    @endunless
                                </div>
                                <div class="flex w-16 shrink-0 flex-col items-end gap-0.5 text-[11px] leading-none">
                                    <span class="flex items-center gap-1"><x-icon name="bolt" class="h-3 w-3 text-amber-300" />{{ $mission['energy'] }}</span>
                                    <span class="flex items-center gap-1"><x-icon name="star" class="h-3 w-3 text-sky-300" />{{ $mission['stars'] }}</span>
                                </div>
                                <div class="w-full shrink-0 text-center sm:w-20">
                                    @if($mission['claimed'])
                                        <span class="flex items-center justify-center gap-1 rounded-lg bg-emerald-400/15 px-2 py-1.5 text-xs font-bold text-emerald-200"><x-icon name="check" class="h-3.5 w-3.5" />Diklaim</span>
                                    @elseif($mission['done'])
                                        <form method="POST" action="{{ route('profile.daily-mission.claim', $mission['key']) }}">
                                            @csrf
                                            <button type="submit" class="w-full rounded-lg bg-gradient-to-b from-amber-400 to-orange-500 px-2 py-1.5 text-xs font-black tracking-wide text-white uppercase shadow-md hover:from-amber-300 hover:to-orange-400">Claim</button>
                                        </form>
                                    @else
                                        <span class="block rounded-lg bg-white/5 px-2 py-1.5 text-xs font-bold text-indigo-200">{{ number_format($mission['actual'],0,',','.') }}/{{ number_format($mission['target'],0,',','.') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-white/10 bg-[#161c3a] p-4">
                            <p class="text-xs font-black tracking-wide text-indigo-200 uppercase">This Week</p>
                            <p class="mt-0.5 text-[11px] text-indigo-300">Reset dalam <span class="font-semibold text-white" data-countdown-target="{{ $weekResetAt }}">--:--:--</span></p>
                            <div class="mt-3 flex items-center gap-1.5">
                                <x-icon name="bolt" class="h-4 w-4 text-amber-300" />
                                <strong class="text-lg">{{ number_format($weekEnergy,0,',','.') }}</strong>
                            </div>
                            <div class="mt-4 space-y-2">
                                @foreach($weeklyChestTiers as $tier)
                                    @php $reached = $weekEnergy >= $tier; @endphp
                                    <div class="flex items-center gap-2 rounded-lg {{ $reached ? 'bg-emerald-400/10' : 'bg-white/5' }} px-2.5 py-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $reached ? 'bg-emerald-400/20 text-emerald-200' : 'bg-white/10 text-indigo-300' }}">
                                            @if($reached)<x-icon name="check" class="h-3.5 w-3.5" />@else<x-icon name="chest" class="h-3.5 w-3.5" />@endif
                                        </span>
                                        <span class="text-[11px] font-semibold {{ $reached ? 'text-emerald-200' : 'text-indigo-300' }}">{{ $reached ? 'Claimed' : 'Needed '.$tier }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/10 bg-[#161c3a] p-4">
                            <p class="text-xs font-black tracking-wide text-indigo-200 uppercase">This Month</p>
                            <p class="mt-0.5 text-[11px] text-indigo-300">Reset dalam <span class="font-semibold text-white" data-countdown-target="{{ $monthResetAt }}">--:--:--</span></p>
                            <div class="mt-3 flex items-center gap-1.5">
                                <x-icon name="bolt" class="h-4 w-4 text-amber-300" />
                                <strong class="text-lg">{{ number_format($monthEnergy,0,',','.') }}</strong>
                            </div>
                            <div class="mt-4 space-y-2">
                                @foreach($monthlyChestTiers as $tier)
                                    @php $reached = $monthEnergy >= $tier; @endphp
                                    <div class="flex items-center gap-2 rounded-lg {{ $reached ? 'bg-emerald-400/10' : 'bg-white/5' }} px-2.5 py-2">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $reached ? 'bg-emerald-400/20 text-emerald-200' : 'bg-white/10 text-indigo-300' }}">
                                            @if($reached)<x-icon name="check" class="h-3.5 w-3.5" />@else<x-icon name="chest" class="h-3.5 w-3.5" />@endif
                                        </span>
                                        <span class="text-[11px] font-semibold {{ $reached ? 'text-emerald-200' : 'text-indigo-300' }}">{{ $reached ? 'Claimed' : 'Needed '.$tier }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
                <section class="profile-stat-strip grid grid-cols-2 gap-3 sm:grid-cols-5">@foreach([['Kegiatan',$stats['reports']],['Leads',$stats['leads']],['Closing',$stats['closing']],['Hari aktif',$stats['active_days']],['Skor',$score]] as [$label,$value])<article class="rounded-2xl border border-white/10 bg-[#35385f] p-3 last:col-span-2 sm:p-4 sm:last:col-span-1"><span class="text-xs text-indigo-200">{{ $label }}</span><strong class="mt-2 block text-xl sm:text-2xl">{{ number_format($value,0,',','.') }}</strong></article>@endforeach</section>
                <section id="pencapaian" class="rounded-2xl border border-white/10 bg-[#35385f] p-5">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-base font-bold">Badge & League</h2>
                        <span class="rounded-lg bg-white/10 px-3 py-1 text-xs font-semibold text-indigo-200">{{ collect($badges)->where('ok', true)->count() }}/{{ count($badges) }} terbuka</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 min-[420px]:grid-cols-3 sm:grid-cols-4 xl:grid-cols-7 sm:gap-3">
                        @foreach ($badges as $badge)
                            <div x-data="{ open: false }">
                                <button type="button" @click="open = true" class="flex w-full flex-col items-center gap-2 rounded-2xl border {{ $badge['ok'] ? 'border-white/15' : 'border-white/5' }} bg-[#212446] p-3 text-center transition hover:border-white/30 focus:outline-none focus:ring-2 focus:ring-white/30">
                                    <span class="relative flex h-14 w-14 items-center justify-center rounded-full" style="background: {{ $badge['ok'] ? 'color-mix(in srgb, var(--color-tone-'.$badge['tone'].') 22%, transparent)' : 'rgba(255,255,255,.06)' }}; color: {{ $badge['ok'] ? 'var(--color-tone-'.$badge['tone'].')' : 'rgba(199,210,254,.5)' }}">
                                        <x-icon name="{{ $badge['icon'] }}" class="h-7 w-7 {{ $badge['ok'] ? '' : 'opacity-70 grayscale' }}" />
                                        @unless ($badge['ok'])
                                            <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full border border-[#212446] bg-[#101227] text-indigo-300"><x-icon name="lock" class="h-3 w-3" /></span>
                                        @endunless
                                    </span>
                                    <span class="line-clamp-2 text-[11px] font-bold {{ $badge['ok'] ? 'text-white' : 'text-indigo-300' }}">{{ $badge['name'] }}</span>
                                    @if ($badge['target'] > 0)
                                        <div class="w-full">
                                            <div class="h-1 overflow-hidden rounded-full bg-white/10"><div class="progress-fill h-full rounded-full {{ $badge['ok'] ? 'bg-emerald-400' : 'bg-indigo-400/60' }}" style="width: {{ $badge['progress_percent'] }}%"></div></div>
                                            <span class="mt-1 block text-[10px] text-indigo-300">{{ number_format(min($badge['actual'], $badge['target']), 0, ',', '.') }}/{{ number_format($badge['target'], 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                </button>

                                <div x-show="open" x-cloak @keydown.escape.window="open = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(15,23,42,0.65)">
                                    <div @click.outside="open = false" class="w-full max-w-sm rounded-2xl border border-white/10 bg-[#212446] p-5 text-white shadow-2xl">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex items-center gap-3">
                                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full" style="background: {{ $badge['ok'] ? 'color-mix(in srgb, var(--color-tone-'.$badge['tone'].') 22%, transparent)' : 'rgba(255,255,255,.06)' }}; color: {{ $badge['ok'] ? 'var(--color-tone-'.$badge['tone'].')' : 'rgba(199,210,254,.5)' }}"><x-icon name="{{ $badge['icon'] }}" class="h-5 w-5 {{ $badge['ok'] ? '' : 'opacity-70 grayscale' }}" /></span>
                                                <h3 class="text-base font-bold">{{ $badge['name'] }}</h3>
                                            </div>
                                            <button type="button" @click="open = false" class="rounded-md border border-white/10 px-2 py-1 text-xs text-indigo-200 hover:text-white">Tutup</button>
                                        </div>
                                        <span class="mt-3 inline-block rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge['ok'] ? 'bg-emerald-400/20 text-emerald-200' : 'bg-white/10 text-indigo-200' }}">{{ $badge['ok'] ? 'Unlocked' : 'Locked' }}</span>
                                        <p class="mt-3 text-sm text-indigo-100">{{ $badge['condition'] }}</p>
                                        @if ($badge['target'] > 0)
                                            <p class="mt-2 text-xs text-indigo-300">Progress: {{ number_format($badge['actual'], 0, ',', '.') }} / {{ number_format($badge['target'], 0, ',', '.') }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
                <section id="aktivitas" class="rounded-2xl border border-white/10 bg-[#35385f] p-3 sm:p-5"><h2 class="mb-3 text-base font-bold">Aktivitas Terbaru</h2><div class="space-y-2">@forelse($reports as $report)<a href="{{ route('reports.show',$report) }}" class="block rounded-xl border border-white/10 p-3 hover:bg-white/10"><div class="flex flex-col gap-1 sm:flex-row sm:justify-between sm:gap-3"><strong class="break-words text-sm">{{ $report->title ?: $report->campaign_name }}</strong><span class="shrink-0 text-xs text-indigo-200">{{ optional($report->report_date)->format('d/m/Y') }}</span></div><p class="mt-1 break-words text-xs text-indigo-200">{{ $report->report_type }} · {{ $report->status }} · {{ $report->leads_count }} leads · {{ $report->closing_count }} closing</p></a>@empty<p class="text-sm text-indigo-200">Belum ada aktivitas.</p>@endforelse</div></section>
            </div>
        </div>
    </div>
</x-layouts.app>
