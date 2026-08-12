<x-layouts.app title="Profil Saya" active="profile">
    <div class="rounded-3xl bg-[#101227] p-4 text-white shadow-2xl sm:p-6">
        <div class="grid gap-5 lg:grid-cols-[270px_1fr]">
            <aside class="rounded-2xl border border-white/10 bg-[#212446] p-5 text-center">
                <div class="relative mx-auto h-40 w-40">
                    <div class="flex h-40 w-40 items-center justify-center overflow-hidden rounded-2xl border-2 border-white/60 bg-gradient-to-br from-emerald-400 to-sky-400 text-4xl font-black shadow-xl">
                        @if($user->photo_path)<img src="{{ $user->photoUrl() }}" alt="{{ $user->name }}" class="h-full w-full object-cover">@else{{ strtoupper(mb_substr($user->name ?: 'U',0,1)) }}@endif
                    </div>
                    <label for="profile_photo" title="Ganti foto profil" class="absolute -bottom-2 -right-2 flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border-2 border-[#212446] bg-emerald-500 text-white shadow-lg hover:bg-emerald-400">
                        <x-icon name="edit" class="h-4 w-4" />
                    </label>
                </div>
                <h2 class="mt-4 text-lg font-bold">{{ $user->name }}</h2>
                <p class="text-xs text-indigo-200">{{ $user->username }}</p>
                <div class="mt-3 flex flex-wrap justify-center gap-x-2 text-xs font-semibold text-indigo-100"><span>{{ $user->jabatan ?: \App\Support\RsmRole::label($user->role) }}</span><span>•</span><span>{{ $user->regional ?: 'Wilayah belum diatur' }}</span></div>
                <div class="mt-5 rounded-2xl bg-[#090b1e]/60 p-4 text-left"><div class="flex justify-between text-xs text-indigo-200"><span>Level {{ $level }}</span><strong class="text-white">{{ number_format($xp,0,',','.') }} XP</strong></div><div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-sky-400" style="width:{{ $levelProgress }}%"></div></div><p class="mt-2 text-xs text-indigo-200">League {{ $league }}</p><p class="mt-1 text-[11px] text-indigo-300">{{ number_format($xpIntoLevel,0,',','.') }} / {{ number_format($xpNeeded,0,',','.') }} XP menuju Level {{ $level + 1 }}</p></div>
                <nav class="mt-5 grid gap-1 text-left text-sm font-semibold text-indigo-100"><a href="#ringkasan" class="rounded-xl bg-black/25 px-3 py-2">Ringkasan</a><a href="#daily-mission" class="rounded-xl px-3 py-2 hover:bg-black/25">Daily Mission</a><a href="#pencapaian" class="rounded-xl px-3 py-2 hover:bg-black/25">Pencapaian</a><a href="#aktivitas" class="rounded-xl px-3 py-2 hover:bg-black/25">Aktivitas</a><a href="#pengaturan" class="rounded-xl px-3 py-2 hover:bg-black/25">Pengaturan Profil</a></nav>
            </aside>
            <div class="space-y-5">
                <section id="ringkasan" class="grid gap-3 sm:grid-cols-3"><article class="rounded-2xl border border-white/10 bg-[#35385f] p-4"><span class="text-xs text-indigo-200">League</span><strong class="mt-2 block text-xl">{{ $league }}</strong><small class="text-indigo-200">XP dan konsistensi</small></article><article class="rounded-2xl border border-white/10 bg-[#35385f] p-4"><span class="text-xs text-indigo-200">Aktivitas</span><strong class="mt-2 block text-xl">{{ number_format($stats['reports'],0,',','.') }}</strong><small class="text-indigo-200">Total kegiatan</small></article><article class="rounded-2xl border border-white/10 bg-[#35385f] p-4"><span class="text-xs text-indigo-200">Aura</span><strong class="mt-2 block text-xl">{{ $score }}/100</strong><small class="text-indigo-200">Skor performa</small></article></section>
                <section id="daily-mission" class="rounded-2xl border border-white/10 bg-gradient-to-b from-[#1c2b52] to-[#111a33] p-5">
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
                            <div class="h-full rounded-full bg-gradient-to-r from-amber-300 to-emerald-300" style="width: {{ min(100, ($todayEnergy / max($dailyChestTiers)) * 100) }}%"></div>
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
                            <div class="flex items-center gap-3 rounded-xl border {{ $mission['claimed'] ? 'border-emerald-300/30 bg-emerald-400/5' : 'border-white/10 bg-[#212446]' }} p-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-bold tracking-wide uppercase">{{ $mission['label'] }}@if($mission['tier'])<span class="ml-1.5 text-[10px] font-semibold text-indigo-300">({{ $mission['tier'] }})</span>@endif</p>
                                    @unless($mission['done'])
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-sky-300" style="width: {{ $mission['progress'] }}%"></div></div>
                                    @endunless
                                </div>
                                <div class="flex w-16 shrink-0 flex-col items-end gap-0.5 text-[11px] leading-none">
                                    <span class="flex items-center gap-1"><x-icon name="bolt" class="h-3 w-3 text-amber-300" />{{ $mission['energy'] }}</span>
                                    <span class="flex items-center gap-1"><x-icon name="star" class="h-3 w-3 text-sky-300" />{{ $mission['stars'] }}</span>
                                </div>
                                <div class="w-20 shrink-0 text-center">
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
                <section class="rounded-2xl border border-white/10 bg-[#35385f] p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold uppercase tracking-widest text-sky-300">Profil User</p><h2 class="mt-1 text-2xl font-bold">{{ $user->name }}</h2><p class="mt-2 text-sm text-indigo-100">{{ $user->bio_text ?: 'Biodata singkat belum diisi.' }}</p></div><div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-400 to-sky-400 text-2xl font-black">@if($user->photo_path)<img src="{{ $user->photoUrl() }}" alt="{{ $user->name }}" class="h-full w-full object-cover">@else{{ strtoupper(mb_substr($user->name ?: 'U',0,1)) }}@endif</div></div></section>
                <section class="grid gap-3 sm:grid-cols-5">@foreach([['Kegiatan',$stats['reports']],['Leads',$stats['leads']],['Closing',$stats['closing']],['Hari aktif',$stats['active_days']],['Skor',$score]] as [$label,$value])<article class="rounded-2xl border border-white/10 bg-[#35385f] p-4"><span class="text-xs text-indigo-200">{{ $label }}</span><strong class="mt-2 block text-2xl">{{ number_format($value,0,',','.') }}</strong></article>@endforeach</section>
                <section id="pencapaian" class="rounded-2xl border border-white/10 bg-[#35385f] p-5"><h2 class="mb-3 text-base font-bold">Badge dan Achievement</h2><div class="flex flex-wrap gap-2">@foreach($badges as $badge)<span class="rounded-full px-3 py-1 text-xs font-semibold {{ $badge['ok'] ? 'bg-emerald-400/20 text-emerald-200' : 'bg-white/10 text-indigo-200' }}">{{ $badge['ok'] ? '✓' : '○' }} {{ $badge['name'] }}</span>@endforeach</div></section>
                <section id="aktivitas" class="rounded-2xl border border-white/10 bg-[#35385f] p-5"><h2 class="mb-3 text-base font-bold">Aktivitas Terbaru</h2><div class="space-y-2">@forelse($reports as $report)<a href="{{ route('reports.show',$report) }}" class="block rounded-xl border border-white/10 p-3 hover:bg-white/10"><div class="flex justify-between gap-3"><strong class="text-sm">{{ $report->title ?: $report->campaign_name }}</strong><span class="text-xs text-indigo-200">{{ optional($report->report_date)->format('d/m/Y') }}</span></div><p class="text-xs text-indigo-200">{{ $report->report_type }} · {{ $report->status }} · {{ $report->leads_count }} leads · {{ $report->closing_count }} closing</p></a>@empty<p class="text-sm text-indigo-200">Belum ada aktivitas.</p>@endforelse</div></section>
                <section id="pengaturan" class="rounded-2xl border border-white/10 bg-[#35385f] p-5"><h2 class="text-base font-bold">Pengaturan Profil</h2><form class="mt-4 grid gap-3" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf<textarea name="bio_text" maxlength="800" rows="4" class="rounded-lg border-white/10 bg-[#212446] text-white" placeholder="Biodata singkat">{{ old('bio_text', $user->bio_text) }}</textarea><input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="text-sm text-indigo-100"><button class="w-fit rounded-lg bg-emerald-500 px-4 py-2 text-sm font-bold text-white">Simpan Profil</button></form></section>
            </div>
        </div>
    </div>
</x-layouts.app>
