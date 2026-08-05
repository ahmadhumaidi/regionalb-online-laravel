<x-layouts.app title="Profil Saya" active="profile">
    <div class="grid gap-5 lg:grid-cols-[280px_1fr]">
        <section class="rounded-2xl border border-border bg-surface p-5 text-center">
            <div class="mx-auto flex h-24 w-24 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-3xl font-bold text-white">
                @if($user->photo_path)<img src="{{ str_starts_with($user->photo_path, 'profiles/') ? Storage::url($user->photo_path) : $user->photo_path }}" alt="{{ $user->name }}" class="h-full w-full object-cover">@else{{ strtoupper(mb_substr($user->name ?: 'U',0,1)) }}@endif
            </div>
            <h2 class="mt-4 text-lg font-semibold text-ink">{{ $user->name }}</h2><p class="text-sm text-ink-muted">{{ $user->username }}</p>
            <div class="mt-4 space-y-1 text-left text-sm text-ink-muted"><p>{{ $user->jabatan ?: \App\Support\RsmRole::label($user->role) }}</p><p>{{ $user->campus_name ?: 'Unit belum diatur' }}</p><p>{{ $user->regional ?: 'Wilayah belum diatur' }}</p><p>{{ $user->phone_number ?: 'No. HP belum terisi' }}</p></div>
        </section>
        <div class="space-y-5">
            <section class="grid gap-3 sm:grid-cols-4">@foreach([['Total kegiatan',$stats['reports']],['Total leads',$stats['leads']],['Total closing',$stats['closing']],['Hari aktif',$stats['active_days']]] as [$label,$value])<article class="rounded-2xl border border-border bg-surface p-4"><span class="text-xs text-ink-muted">{{ $label }}</span><strong class="mt-2 block text-2xl text-ink">{{ number_format($value,0,',','.') }}</strong></article>@endforeach</section>
            <section class="rounded-2xl border border-border bg-surface p-5"><h2 class="text-base font-semibold text-ink">Pengaturan Profil</h2><form class="mt-4 grid gap-3" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">@csrf<textarea name="bio_text" maxlength="800" rows="4" class="rounded-lg border-border bg-surface-muted" placeholder="Biodata singkat">{{ old('bio_text', $user->bio_text) }}</textarea><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="text-sm"><button class="w-fit rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Simpan Profil</button></form></section>
            <section class="rounded-2xl border border-border bg-surface p-5"><h2 class="mb-3 text-base font-semibold text-ink">Aktivitas Terbaru</h2><div class="space-y-2">@forelse($reports as $report)<a href="{{ route('reports.show',$report) }}" class="block rounded-lg border border-border/70 p-3 hover:bg-brand-50"><div class="flex justify-between gap-3"><strong class="text-sm text-ink">{{ $report->title ?: $report->campaign_name }}</strong><span class="text-xs text-ink-muted">{{ optional($report->report_date)->format('d/m/Y') }}</span></div><p class="text-xs text-ink-muted">{{ $report->report_type }} · {{ $report->status }} · {{ $report->leads_count }} leads · {{ $report->closing_count }} closing</p></a>@empty<p class="text-sm text-ink-muted">Belum ada aktivitas.</p>@endforelse</div></section>
        </div>
    </div>
</x-layouts.app>
