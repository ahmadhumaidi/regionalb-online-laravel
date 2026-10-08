<x-layouts.app :title="$editing ? 'Edit Laporan Kegiatan' : 'Tambah Laporan Kegiatan'" active="laporan-senior">
    <section class="mx-auto max-w-4xl rounded-2xl glass-card p-5">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div><h2 class="text-base font-semibold text-ink">{{ $editing ? 'Edit' : 'Buat' }} laporan Senior Manager</h2><p class="mt-1 text-sm text-ink-muted">Catat kegiatan kunjungan atau rapat beserta hasil dan tindak lanjutnya.</p></div>
            <a href="{{ route('laporan-senior.index') }}" class="rounded-lg border border-border px-3 py-2 text-sm">Kembali</a>
        </div>
        <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('laporan-senior.update', $report) : route('laporan-senior.store') }}" class="grid gap-4 md:grid-cols-2">
            @csrf @if($editing) @method('PATCH') @endif
            @if($errors->any())<div class="md:col-span-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <label class="grid gap-1 text-sm">Tanggal kegiatan<input type="date" name="activity_date" required value="{{ old('activity_date', optional($report->activity_date)->format('Y-m-d') ?: now('Asia/Jakarta')->toDateString()) }}" class="rounded-lg border-border bg-surface-muted"></label>
            <label class="grid gap-1 text-sm">Jenis kegiatan<select name="activity_type" required class="rounded-lg border-border bg-surface-muted"><option value="">Pilih jenis</option>@foreach(['Kunjungan','Rapat'] as $type)<option value="{{ $type }}" @selected(old('activity_type', $report->activity_type) === $type)>{{ $type }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-sm md:col-span-2">Judul kegiatan<input name="title" required maxlength="220" value="{{ old('title', $report->title) }}" placeholder="Contoh: Kunjungan evaluasi PMB Kampus X" class="rounded-lg border-border bg-surface-muted"></label>
            <label class="grid gap-1 text-sm">Lokasi / media rapat<input name="location" maxlength="220" value="{{ old('location', $report->location) }}" placeholder="Kampus, kantor, Zoom, Google Meet" class="rounded-lg border-border bg-surface-muted"></label>
            <label class="grid gap-1 text-sm">Peserta<input name="participants" value="{{ old('participants', $report->participants) }}" placeholder="Nama atau unit yang hadir" class="rounded-lg border-border bg-surface-muted"></label>
            <label class="grid gap-1 text-sm md:col-span-2">Agenda<textarea name="agenda" required rows="3" class="rounded-lg border-border bg-surface-muted">{{ old('agenda', $report->agenda) }}</textarea></label>
            <label class="grid gap-1 text-sm md:col-span-2">Hasil / keputusan<textarea name="result_text" required rows="4" class="rounded-lg border-border bg-surface-muted">{{ old('result_text', $report->result_text) }}</textarea></label>
            <label class="grid gap-1 text-sm md:col-span-2">Tindak lanjut<textarea name="next_action" rows="3" class="rounded-lg border-border bg-surface-muted">{{ old('next_action', $report->next_action) }}</textarea></label>
            <label class="grid gap-1 text-sm md:col-span-2">Dokumentasi<input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf" class="rounded-lg border-border bg-surface-muted"><span class="text-xs text-ink-muted">JPG, PNG, WEBP, atau PDF; maksimal 5 MB.</span></label>
            @if($editing && $report->attachment_path)<a href="{{ route('laporan-senior.attachment', $report) }}" target="_blank" class="text-sm font-semibold text-brand-700">Lihat dokumentasi saat ini</a>@endif
            <div class="md:col-span-2"><button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">{{ $editing ? 'Simpan Perubahan' : 'Simpan Laporan' }}</button></div>
        </form>
    </section>
</x-layouts.app>
