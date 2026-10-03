<x-layouts.app title="Upload Konten Sosmed" active="upload-konten-sosmed">
    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <p class="font-semibold">Data belum dapat disimpan.</p>
            <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="mb-5 overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-700 via-brand-700 to-cyan-600 p-5 text-white shadow-lg sm:p-7">
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
            <div class="max-w-3xl">
                <p class="text-xs font-bold tracking-[0.18em] text-cyan-100 uppercase">Konten & Kegiatan</p>
                <h2 class="mt-2 text-2xl font-bold">Upload Konten Sosmed</h2>
                <p class="mt-2 text-sm leading-6 text-indigo-100">Catat Feed, Reels, Story, dan TikTok langsung di sistem. Data otomatis masuk ke Monitoring Konten Kampus dan Journey Staff Unit.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                @if ($sheetUrls['feed'])
                    <a href="{{ $sheetUrls['feed'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/25"><x-icon name="document" class="h-4 w-4" /> Arsip Feed</a>
                @endif
                @if ($sheetUrls['story'])
                    <a href="{{ $sheetUrls['story'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/30 transition hover:bg-white/25"><x-icon name="document" class="h-4 w-4" /> Arsip Story</a>
                @endif
            </div>
        </div>
    </section>

    <section class="mb-6 overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
            <div>
                <h2 class="font-semibold text-ink">Rekap Aktivitas September</h2>
                <p class="mt-0.5 text-xs text-ink-muted">Regional 4–7 · aktivitas Feed dan Story lengkap tanggal 1–30 September.</p>
            </div>
            @if ($spreadsheetRecap['synced_at'])
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Diperbarui {{ \Carbon\Carbon::parse($spreadsheetRecap['synced_at'])->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</span>
            @endif
        </div>
        @if ($spreadsheetRecap['error'])
            <div class="m-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">{{ $spreadsheetRecap['error'] }}</div>
        @elseif (empty($spreadsheetRecap['rows']))
            <div class="px-5 py-10 text-center text-sm text-ink-muted">Belum ada data rekap yang dapat ditampilkan.</div>
        @else
            <div class="border-b border-border bg-cyan-50/60 px-5 py-2 text-xs text-cyan-800">Geser tabel ke kanan untuk melihat tanggal 1–30. <strong>F</strong> = Feed, <strong>S</strong> = Story.</div>
            <div class="max-h-[70vh] overflow-auto overscroll-contain">
                <table class="w-max min-w-full border-separate border-spacing-0 text-left text-sm">
                    <thead class="text-xs text-ink-muted">
                        <tr class="bg-surface-muted">
                            <th rowspan="2" class="sticky top-0 left-0 z-[15] w-40 min-w-40 border-r border-b border-border bg-surface-muted px-3 py-3 font-semibold shadow-[3px_0_8px_rgba(15,23,42,0.10)] sm:w-48 sm:min-w-48 sm:px-4">Staff Unit</th>
                            <th rowspan="2" class="sticky top-0 z-10 w-24 min-w-24 border-r border-b border-border bg-surface-muted px-3 py-3 font-semibold">Regional</th>
                            <th rowspan="2" class="sticky top-0 z-10 w-64 min-w-64 border-r border-b border-border bg-surface-muted px-4 py-3 font-semibold">Nama Kampus</th>
                            <th rowspan="2" class="sticky top-0 z-10 min-w-32 border-r border-b border-border bg-surface-muted px-4 py-3 font-semibold">Profil</th>
                            @for ($day = 1; $day <= 30; $day++)
                                <th colspan="2" class="sticky top-0 z-10 border-r border-b border-border bg-surface-muted px-2 py-2 text-center font-bold text-ink">{{ $day }}</th>
                            @endfor
                            <th colspan="3" class="sticky top-0 z-10 border-b border-border bg-brand-50 px-3 py-2 text-center font-bold text-brand-700">Total September</th>
                        </tr>
                        <tr class="bg-surface-muted/80">
                            @for ($day = 1; $day <= 30; $day++)
                                <th class="sticky top-8 z-10 w-10 min-w-10 border-r border-b border-border bg-surface-muted px-1 py-1.5 text-center font-semibold text-cyan-700">F</th>
                                <th class="sticky top-8 z-10 w-10 min-w-10 border-r border-b border-border bg-surface-muted px-1 py-1.5 text-center font-semibold text-purple-700">S</th>
                            @endfor
                            <th class="sticky top-8 z-10 w-14 min-w-14 border-r border-b border-border bg-brand-50 px-2 py-1.5 text-center font-semibold text-cyan-700">Feed</th>
                            <th class="sticky top-8 z-10 w-14 min-w-14 border-r border-b border-border bg-brand-50 px-2 py-1.5 text-center font-semibold text-purple-700">Story</th>
                            <th class="sticky top-8 z-10 w-14 min-w-14 border-b border-border bg-brand-50 px-2 py-1.5 text-center font-semibold text-emerald-700">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($spreadsheetRecap['rows'] as $row)
                            <tr class="hover:bg-surface-muted/60">
                                <td class="sticky left-0 z-[5] max-w-40 border-r border-b border-border bg-white px-3 py-2.5 font-medium text-ink shadow-[3px_0_8px_rgba(15,23,42,0.08)] sm:max-w-48 sm:px-4">{{ $row['staff'] ?: '—' }}</td>
                                <td class="whitespace-nowrap border-r border-b border-border bg-white px-3 py-2.5"><span class="rounded-full bg-brand-50 px-2 py-1 text-[11px] font-semibold text-brand-700">{{ str_replace('Regional ', 'R', $row['regional']) }}</span></td>
                                <td class="border-r border-b border-border bg-white px-4 py-2.5 font-medium text-ink">{{ $row['campus'] }}</td>
                                <td class="border-r border-b border-border px-4 py-2.5">@if ($row['profile_url'])<a href="{{ $row['profile_url'] }}" target="_blank" rel="noopener noreferrer" class="whitespace-nowrap font-medium text-brand-600 hover:underline">Buka ↗</a>@else<span class="text-ink-muted">—</span>@endif</td>
                                @for ($day = 1; $day <= 30; $day++)
                                    @php($feedValue = $row['feed_days'][$day] ?? 0)
                                    @php($storyValue = $row['story_days'][$day] ?? 0)
                                    <td class="border-r border-b border-border px-1 py-2 text-center text-xs font-semibold {{ $feedValue > 0 ? 'bg-cyan-50 text-cyan-700' : 'text-ink-muted' }}">{{ $feedValue }}</td>
                                    <td class="border-r border-b border-border px-1 py-2 text-center text-xs font-semibold {{ $storyValue > 0 ? 'bg-purple-50 text-purple-700' : 'text-ink-muted' }}">{{ $storyValue }}</td>
                                @endfor
                                <td class="border-r border-b border-border bg-brand-50/50 px-2 py-2 text-center font-bold text-cyan-700">{{ $row['feed_total'] }}</td>
                                <td class="border-r border-b border-border bg-brand-50/50 px-2 py-2 text-center font-bold text-purple-700">{{ $row['story_total'] }}</td>
                                <td class="border-b border-border bg-emerald-50 px-2 py-2 text-center font-bold text-emerald-700">{{ $row['feed_total'] + $row['story_total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section id="input-aktivitas-konten" class="mb-6 scroll-mt-6 rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <div class="mb-4">
            <h2 class="text-base font-semibold text-ink">Input aktivitas konten</h2>
            <p class="mt-1 text-sm text-ink-muted">Isi link konten yang dikerjakan. Jenis laporan akan terdeteksi otomatis dari kolom yang diisi.</p>
        </div>
        <form method="POST" action="{{ route('upload-konten-sosmed.store') }}" class="space-y-5" x-data="{ regional: @js(old('wilayah', auth()->user()->regional)), username: @js(old('instagram_username', '')), instagramUrl: @js(old('instagram_url', '')), selectedCampus: @js(old('unit_name', auth()->user()->campus_name)), campuses: @js($referenceOptions['campuses']), filteredCampuses() { return this.regional ? this.campuses.filter(item => item.wilayah === this.regional) : []; } }" x-init="const campus = campuses.find(item => item.label === selectedCampus && item.wilayah === regional); if (campus && !username && !instagramUrl) { username = campus.instagram_username || ''; instagramUrl = campus.instagram_url || ''; } else if (!campus) { selectedCampus = ''; }">
            @csrf
            <div class="grid gap-4 md:grid-cols-3">
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-ink-muted">Tanggal</span><input type="date" name="post_date" value="{{ old('post_date', now('Asia/Jakarta')->toDateString()) }}" required class="w-full rounded-xl border-border bg-surface-muted text-sm"></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-ink-muted">Wilayah</span><select name="wilayah" x-model="regional" required @change="selectedCampus = ''; username = ''; instagramUrl = ''" class="w-full rounded-xl border-border bg-surface-muted text-sm"><option value="">Pilih wilayah</option>@foreach ($referenceOptions['regionals'] as $regional)<option value="{{ $regional }}">{{ $regional }}</option>@endforeach</select></label>
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-ink-muted">Unit/Kampus</span><select name="unit_name" x-model="selectedCampus" required :disabled="!regional" @change="const campus = campuses.find(item => item.label === $event.target.value && item.wilayah === regional); username = campus?.instagram_username || ''; instagramUrl = campus?.instagram_url || '';" class="w-full rounded-xl border-border bg-surface-muted text-sm disabled:cursor-not-allowed disabled:opacity-60"><option value="">Pilih kampus</option><template x-for="campus in filteredCampuses()" :key="campus.id || campus.label"><option :value="campus.label" x-text="campus.label"></option></template></select></label>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <section class="rounded-2xl border border-fuchsia-200 bg-fuchsia-50/60 p-4">
                    <div class="mb-3"><h3 class="font-black text-fuchsia-800">Instagram</h3><p class="text-xs text-fuchsia-700/70">Feed, Reels, dan Story Instagram</p></div>
                    <div class="mb-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2"><label class="block"><span class="mb-1 block text-[11px] font-semibold text-slate-500">Username</span><input name="instagram_username" x-model="username" placeholder="@username_kampus" class="w-full rounded-lg border-fuchsia-200 bg-white text-xs"></label><label class="block"><span class="mb-1 block text-[11px] font-semibold text-slate-500">Link profil</span><input type="url" name="instagram_url" x-model="instagramUrl" placeholder="https://instagram.com/..." class="w-full rounded-lg border-fuchsia-200 bg-white text-xs"></label></div>
                    <div class="space-y-2">@foreach (['feed'=>'Feed','reels'=>'Reels','story'=>'Story'] as $type=>$label)<label class="block rounded-xl border border-fuchsia-100 bg-white p-2.5"><span class="text-sm font-semibold">{{ $label }}</span><input type="url" name="post_urls[{{ $type }}]" value="{{ old('post_urls.'.$type) }}" placeholder="Link {{ $label }} Instagram" class="mt-2 w-full rounded-lg border-slate-200 text-xs"></label>@endforeach</div>
                </section>

                <section class="rounded-2xl border border-blue-200 bg-blue-50/60 p-4">
                    <div class="mb-3"><h3 class="font-black text-blue-800">Facebook</h3><p class="text-xs text-blue-700/70">Posting konten Facebook</p></div>
                    <label class="block rounded-xl border border-blue-100 bg-white p-3"><span class="text-sm font-semibold">Link konten Facebook</span><input type="url" name="post_urls[facebook]" value="{{ old('post_urls.facebook') }}" placeholder="https://facebook.com/..." class="mt-3 w-full rounded-lg border-slate-200 text-xs"></label>
                </section>

                <section class="rounded-2xl border border-slate-300 bg-slate-100/70 p-4">
                    <div class="mb-3"><h3 class="font-black text-slate-900">TikTok</h3><p class="text-xs text-slate-500">Video TikTok harian</p></div>
                    <label class="block rounded-xl border border-slate-200 bg-white p-3"><span class="text-sm font-semibold">Link video TikTok</span><input type="url" name="post_urls[tiktok]" value="{{ old('post_urls.tiktok') }}" placeholder="https://tiktok.com/..." class="mt-3 w-full rounded-lg border-slate-200 text-xs"></label>
                </section>
            </div>

            <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <label class="block"><span class="mb-1.5 block text-xs font-semibold text-ink-muted">Catatan/caption (opsional)</span><textarea name="caption" rows="2" placeholder="Tema atau keterangan singkat konten" class="w-full rounded-xl border-border bg-surface-muted text-sm">{{ old('caption') }}</textarea></label>
                <div class="flex flex-wrap items-center gap-3"><label class="flex items-center gap-2 text-sm text-ink"><input type="checkbox" name="keyword_match" value="1" @checked(old('keyword_match')) class="rounded border-border text-brand-600 focus:ring-brand-500"> Memuat keyword PMB</label><button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700"><x-icon name="cloud" class="h-4 w-4" /> Simpan konten</button></div>
            </div>
        </form>
    </section>

    <form method="GET" action="{{ route('upload-konten-sosmed') }}" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-surface p-4 shadow-sm" x-data="{ regional: @js($filters['wilayah']), selectedCampus: @js($filters['unitName']), campuses: @js($referenceOptions['campuses']), filteredCampuses() { return this.regional ? this.campuses.filter(item => item.wilayah === this.regional) : this.campuses; } }">
        <label><span class="mb-1 block text-xs font-medium text-ink-muted">Dari tanggal</span><input type="date" name="date_from" value="{{ $filters['dateFrom'] }}" class="rounded-lg border-border text-sm"></label>
        <label><span class="mb-1 block text-xs font-medium text-ink-muted">Sampai tanggal</span><input type="date" name="date_to" value="{{ $filters['dateTo'] }}" class="rounded-lg border-border text-sm"></label>
        <label><span class="mb-1 block text-xs font-medium text-ink-muted">Wilayah</span><select name="wilayah" x-model="regional" @change="selectedCampus = ''" class="rounded-lg border-border text-sm"><option value="">Semua wilayah</option>@foreach ($referenceOptions['regionals'] as $regional)<option value="{{ $regional }}">{{ $regional }}</option>@endforeach</select></label>
        <label class="min-w-56"><span class="mb-1 block text-xs font-medium text-ink-muted">Unit/Kampus</span><select name="unit_name" x-model="selectedCampus" class="w-full rounded-lg border-border text-sm"><option value="">Semua kampus</option><template x-for="campus in filteredCampuses()" :key="campus.id || campus.label"><option :value="campus.label" x-text="campus.label"></option></template></select></label>
        <div class="ml-auto flex gap-2"><button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Terapkan</button><a href="{{ route('upload-konten-sosmed') }}" class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-ink-muted">Reset</a></div>
    </form>

    <x-dashboard.summary-cards :cards="$summaryCards" />

    <section class="mt-6 overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
        <div class="border-b border-border px-5 py-4"><h2 class="font-semibold text-ink">Riwayat konten</h2><p class="mt-0.5 text-xs text-ink-muted">Data baru yang dicatat melalui sistem.</p></div>
        @if ($posts->isEmpty())
            <div class="px-5 py-12 text-center text-sm text-ink-muted">Belum ada konten pada periode dan filter ini.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="bg-surface-muted text-xs text-ink-muted"><tr><th class="px-4 py-3 font-semibold">Tanggal</th><th class="px-4 py-3 font-semibold">Kampus</th><th class="px-4 py-3 font-semibold">Akun</th><th class="px-4 py-3 font-semibold">Jenis</th><th class="px-4 py-3 font-semibold">Link</th><th class="px-4 py-3 font-semibold">Penginput</th><th class="px-4 py-3 text-right font-semibold">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($posts as $post)
                            <tr class="align-top hover:bg-surface-muted/60">
                                <td class="px-4 py-3 text-ink-muted">{{ $post->post_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3"><p class="font-medium text-ink">{{ $post->account->unit_name }}</p><p class="text-xs text-ink-muted">{{ $post->account->wilayah }}</p></td>
                                <td class="px-4 py-3 text-ink-muted">{{ '@'.$post->account->instagram_username }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">{{ $mediaLabels[$post->media_type] ?? $post->media_type }}</span></td>
                                <td class="max-w-64 px-4 py-3">@if ($post->post_url)<a href="{{ $post->post_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-brand-600 hover:underline">Lihat konten ↗</a>@else<span class="text-ink-muted">—</span>@endif @if ($post->caption)<p class="mt-1 truncate text-xs text-ink-muted" title="{{ $post->caption }}">{{ $post->caption }}</p>@endif</td>
                                <td class="px-4 py-3 text-ink-muted">{{ $post->created_by_name ?: '—' }}</td>
                                <td class="px-4 py-3"><div class="flex justify-end gap-2"><details class="relative"><summary class="cursor-pointer list-none rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted">Edit</summary><div class="absolute right-0 z-20 mt-2 w-80 rounded-xl border border-border bg-white p-4 shadow-xl"><form method="POST" action="{{ route('upload-konten-sosmed.update', $post) }}" class="space-y-3">@csrf @method('PATCH')<input type="date" name="post_date" value="{{ $post->post_date->toDateString() }}" required class="w-full rounded-lg border-border text-sm"><select name="media_type" class="w-full rounded-lg border-border text-sm">@foreach($mediaLabels as $type => $label)<option value="{{ $type }}" @selected($post->media_type === $type)>{{ $label }}</option>@endforeach</select><input type="url" name="post_url" value="{{ $post->post_url }}" placeholder="Link konten" class="w-full rounded-lg border-border text-sm"><textarea name="caption" rows="2" placeholder="Catatan" class="w-full rounded-lg border-border text-sm">{{ $post->caption }}</textarea><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="keyword_match" value="1" @checked($post->keyword_match)> Keyword PMB</label><button class="w-full rounded-lg bg-brand-600 px-3 py-2 text-xs font-semibold text-white">Simpan perubahan</button></form></div></details><form method="POST" action="{{ route('upload-konten-sosmed.destroy', $post) }}" onsubmit="return confirm('Hapus data konten ini?')">@csrf @method('DELETE')<button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">Hapus</button></form></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-border px-5 py-4">{{ $posts->links() }}</div>
        @endif
    </section>
</x-layouts.app>
