<x-layouts.app title="Journey Staff Unit" active="staff-journey" eyebrow="Daily Team Race">
    @php
        $activities = [
            ['key'=>'absen','title'=>'Absen Masuk','short'=>'Absen','target'=>'Ontime','category'=>'DAILY','icon'=>'calendar','report_notice'=>'Lapor melalui aplikasi absen'],
            ['key'=>'fu_bdc','title'=>'FU BDC','short'=>'FU BDC','target'=>'30 FU/hari','category'=>'DAILY','icon'=>'chat','report_url'=>'https://daftarkuliah.my.id/bdcv2/','external'=>true],
            ['key'=>'instagram','title'=>'Konten Instagram','short'=>'Instagram','target'=>'1/hari','category'=>'DAILY','icon'=>'instagram','report_url'=>route('upload-konten-sosmed').'#input-aktivitas-konten'],
            ['key'=>'facebook','title'=>'Konten Facebook','short'=>'Facebook','target'=>'1/hari','category'=>'DAILY','icon'=>'facebook','report_url'=>route('upload-konten-sosmed').'#input-aktivitas-konten'],
            ['key'=>'tiktok','title'=>'TikTok','short'=>'TikTok','target'=>'1/hari','category'=>'DAILY','icon'=>'tiktok','report_url'=>route('upload-konten-sosmed').'#input-aktivitas-konten'],
            ['key'=>'live_day','title'=>'Live Streaming Day','short'=>'Live Day','target'=>'1 jam/hari','category'=>'DAILY','icon'=>'bolt','report_url'=>'https://cb.web.id/ggklikv2/','external'=>true],
            ['key'=>'story_ig','title'=>'Story Instagram','short'=>'Story IG','target'=>'1/hari','category'=>'DAILY','icon'=>'photo','report_url'=>route('upload-konten-sosmed').'#input-aktivitas-konten'],
            ['key'=>'share_fb','title'=>'Share Konten Facebook','short'=>'Share FB','target'=>'3/hari','category'=>'DAILY','icon'=>'cloud','report_url'=>'https://cb.web.id/ggklikv2/','external'=>true],
            ['key'=>'laporan','title'=>'Laporan Aktivitas Lain','short'=>'Aktivitas Lain','target'=>'1/hari','category'=>'DAILY','icon'=>'clipboard','report_url'=>route('aktivitas.create')],
            ['key'=>'live_night','title'=>'Live Streaming Night','short'=>'Live Night','target'=>'1 jam/minggu','category'=>'WEEKLY','icon'=>'bolt','report_url'=>route('aktivitas.create',['activity'=>'Live Streaming Night'])],
            ['key'=>'affiliate','title'=>'Sapa Grup Affiliate','short'=>'Sapa Affiliate','target'=>'1/minggu','category'=>'WEEKLY','icon'=>'chat','report_url'=>route('aktivitas.create',['activity'=>'Sapa Grup Affiliate'])],
            ['key'=>'canvassing','title'=>'Canvassing','short'=>'Canvassing','target'=>'3/minggu','category'=>'WEEKLY','icon'=>'users','report_notice'=>'Lapor melalui aplikasi absen'],
            ['key'=>'brosur','title'=>'Sebar Brosur','short'=>'Sebar Brosur','target'=>'200/minggu','category'=>'WEEKLY','icon'=>'document','report_notice'=>'Lapor melalui aplikasi absen'],
            ['key'=>'spanduk','title'=>'Spanduk Kerjasama','short'=>'Spanduk','target'=>'6 pcs / 2 bulan','category'=>'PERIODIC','icon'=>'flag','report_url'=>route('aktivitas.create',['activity'=>'Spanduk Kerjasama'])],
        ];
    @endphp
    <div x-data="journeyDashboard(@js($journeyStaff), @js($activities), @js($journeyDate))" x-init="startClock()" class="space-y-5" @keydown.escape.window="selectedStaff = null; selectedActivity = null; selectedCheckpoint = null" @fullscreenchange.window="isFullscreen = document.fullscreenElement === $refs.journeyArena">
        <header class="relative overflow-hidden rounded-3xl bg-[#0f172a] px-5 py-4 text-white shadow-2xl shadow-blue-950/15 sm:px-6 lg:flex lg:items-center lg:justify-between">
            <div class="pointer-events-none absolute -top-24 right-20 h-72 w-72 rounded-full bg-blue-600/30 blur-3xl"></div>
            <div class="relative max-w-3xl"><span class="inline-flex items-center gap-1.5 rounded-full border border-blue-300/20 bg-blue-400/10 px-2.5 py-0.5 text-[10px] font-black tracking-[.18em] text-blue-300"><x-icon name="trophy" class="h-3 w-3" /> DAILY TEAM RACE</span><h2 class="mt-2 text-xl font-black tracking-tight sm:text-2xl">Gamification KPI Staff Unit</h2><p class="mt-1 text-xs text-slate-300 sm:text-sm">Setiap 1 Aktivitas Selesai = Naik 1 Checkpoint</p><p class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-white/10 px-2.5 py-1.5 text-[11px] font-semibold"><span class="text-cyan-300">●</span> Aktivitas bebas dikerjakan, tidak harus urut.</p></div>
            <div class="relative mt-3 grid grid-cols-2 gap-2 lg:mt-0 lg:min-w-[340px]"><div class="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5"><p class="text-[10px] text-slate-400">Hari ini</p><strong class="mt-0.5 block text-xs" x-text="todayLabel"></strong></div><div class="rounded-xl border border-blue-400/20 bg-blue-500/15 px-3 py-2.5"><p class="text-[10px] text-blue-200">Reset Daily</p><strong class="mt-0.5 block font-mono text-lg tracking-wider" x-text="countdown"></strong></div></div>
        </header>
        @if($user->role === 'super_user')
            <section class="rounded-2xl border border-border bg-white/80 p-3 shadow-sm backdrop-blur"><div class="grid gap-2 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1fr_1.3fr]">
                <select x-model="filters.area" class="min-h-11 rounded-xl border-border bg-white px-3 text-sm font-semibold"><option value="">Semua Wilayah</option><option value="Regional A">Regional A</option><option value="Regional B">Regional B</option></select>
                <select x-model="filters.regional" class="min-h-11 rounded-xl border-border bg-white px-3 text-sm font-semibold"><option value="">Semua Regional</option><template x-for="item in unique('regional')" :key="item"><option x-text="item"></option></template></select>
                <select x-model="filters.campus" class="min-h-11 rounded-xl border-border bg-white px-3 text-sm font-semibold"><option value="">Semua Kampus</option><template x-for="item in unique('campus')" :key="item"><option x-text="item"></option></template></select>
                <select x-model="filters.unit" class="min-h-11 rounded-xl border-border bg-white px-3 text-sm font-semibold"><option value="">Semua Unit</option><template x-for="item in unique('unit')" :key="item"><option x-text="item"></option></template></select>
                <label class="relative"><x-icon name="calendar" class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-ink-muted"/><input type="date" value="{{ $journeyDate }}" max="{{ now()->toDateString() }}" onchange="window.location.href='{{ route('staff-journey') }}?date='+this.value" class="min-h-11 w-full rounded-xl border-border bg-white pl-9 pr-3 text-sm font-semibold"></label>
                <input x-model="filters.query" placeholder="Cari staff..." class="min-h-11 rounded-xl border-border bg-white px-3 text-sm md:col-span-2 xl:col-span-1">
            </div></section>
        @endif
        @include('staff-journey.partials.track')
        @if($trackedDaily < 9)<div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><span class="mt-0.5">⚠</span><p><strong>{{ $trackedDaily }} dari 9 KPI daily sudah terhubung ke data nyata.</strong> KPI yang belum memiliki sumber data tidak dihitung selesai sampai integrasinya tersedia.</p></div>@endif
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-5"><template x-for="card in statCards" :key="card.label"><article class="rounded-2xl border border-white bg-white p-4 shadow-sm last:col-span-2 lg:last:col-span-1"><span class="mb-3 grid h-9 w-9 place-items-center rounded-xl text-lg" :class="card.tone" x-text="card.icon"></span><strong class="block text-2xl font-black tracking-tight text-slate-900" x-text="card.value"></strong><span class="text-sm text-slate-500" x-text="card.label"></span></article></template></section>
        <div class="grid gap-5 xl:grid-cols-[1fr_340px]">@include('staff-journey.partials.activity-pool', ['activities'=>$activities]) @include('staff-journey.partials.leaderboard')</div>
    </div>
    <script>
        function journeyDashboard(staff, activities, journeyDate) { return {
            staff, activities, selectedStaff:null, selectedActivity:null, selectedCheckpoint:null, isFullscreen:false, filters:{area:'',regional:'',campus:'',unit:'',query:''}, countdown:'00:00:00', todayLabel:new Intl.DateTimeFormat('id-ID',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(new Date(journeyDate+'T00:00:00')),
            get filteredStaff(){const q=this.filters.query.toLowerCase();return this.staff.filter(p=>(!this.filters.area||p.area===this.filters.area)&&(!this.filters.regional||p.regional===this.filters.regional)&&(!this.filters.campus||p.campus===this.filters.campus)&&(!this.filters.unit||p.unit===this.filters.unit)&&(!q||`${p.name} ${p.unit} ${p.campus}`.toLowerCase().includes(q)));},
            get completedMissions(){return this.filteredStaff.reduce((sum,p)=>sum+Math.min(p.completed_daily,p.total_daily),0);}, get totalMissions(){return this.filteredStaff.reduce((sum,p)=>sum+p.total_daily,0);}, get teamProgress(){return this.totalMissions?Math.round(this.completedMissions/this.totalMissions*100):0;},
            get statCards(){const total=this.filteredStaff.length,start=this.filteredStaff.filter(p=>p.completed_daily===0).length,finish=this.filteredStaff.filter(p=>p.completed_daily>=p.total_daily).length;return [{label:'Total Player',value:total,icon:'♟',tone:'bg-blue-50 text-blue-600'},{label:'Di Basecamp',value:start,icon:'🚩',tone:'bg-slate-100 text-slate-600'},{label:'Sedang Bertanding',value:total-start-finish,icon:'🔥',tone:'bg-amber-50 text-amber-600'},{label:'Daily Champion',value:finish,icon:'🏆',tone:'bg-emerald-50 text-emerald-600'},{label:'Progress Tim',value:this.teamProgress+'%',icon:'⚡',tone:'bg-violet-50 text-violet-600'}];},
            get leaderboard(){return [...this.filteredStaff].sort((a,b)=>{const af=a.completed_daily>=a.total_daily,bf=b.completed_daily>=b.total_daily;if(af!==bf)return af?-1:1;if(af)return(a.finish_time||'99:99').localeCompare(b.finish_time||'99:99');return b.completed_daily-a.completed_daily||(a.last_activity_at||'99:99').localeCompare(b.last_activity_at||'99:99');}).slice(0,5);},
            activityCount(key,done){return this.filteredStaff.filter(person=>done?person.activities?.[key]?.done===true:person.activities?.[key]?.done!==true).length;},
            activityPeople(state){if(!this.selectedActivity)return[];return this.filteredStaff.filter(person=>{const progress=person.activities?.[this.selectedActivity.key];if(state==='done')return progress?.tracked===true&&progress?.done===true;if(state==='pending')return progress?.tracked===true&&progress?.done!==true;return !progress||progress.tracked!==true;});},
            checkpointPeople(){return this.selectedCheckpoint?this.at(this.selectedCheckpoint.step):[];},
            openStaffFromCheckpoint(person){this.selectedCheckpoint=null;this.selectedStaff=person;},
            async toggleJourneyFullscreen(){
                const arena=this.$refs.journeyArena;
                if(!arena)return;

                const activeFullscreen=document.fullscreenElement||document.webkitFullscreenElement;
                if(this.isFullscreen&&!activeFullscreen){
                    this.isFullscreen=false;
                    document.documentElement.classList.remove('journey-fallback-fullscreen');
                    return;
                }
                if(activeFullscreen===arena){
                    try{await (document.exitFullscreen?.()||document.webkitExitFullscreen?.());}catch(error){}
                    this.isFullscreen=false;
                    return;
                }

                const request=arena.requestFullscreen||arena.webkitRequestFullscreen;
                if(request){
                    try{
                        await request.call(arena);
                        this.isFullscreen=true;
                        return;
                    }catch(error){}
                }

                this.isFullscreen=!this.isFullscreen;
                document.documentElement.classList.toggle('journey-fallback-fullscreen',this.isFullscreen);
            },
            unique(key){return[...new Set(this.staff.map(p=>p[key]))].sort();}, at(progress){return this.filteredStaff.filter(p=>progress===9?p.completed_daily>=9:p.completed_daily===progress);}, percentage(p){return Math.min(100,p.completed_daily/p.total_daily*100);}, status(p){return p.completed_daily===0?'START':(p.completed_daily>=p.total_daily?'FINISH':`CHECKPOINT ${p.completed_daily}`);},
            startClock(){const tick=()=>{const now=new Date(),reset=new Date(now);reset.setHours(24,0,0,0);const s=Math.max(0,Math.floor((reset-now)/1000));this.countdown=[Math.floor(s/3600),Math.floor(s%3600/60),s%60].map(v=>String(v).padStart(2,'0')).join(':');};tick();setInterval(tick,1000);}
        };}
    </script>
</x-layouts.app>
