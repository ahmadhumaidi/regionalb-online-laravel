<x-layouts.app title="Jadwal Koordinator" active="jadwal-koordinator">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if (auth()->user()->role === 'super_user')
        <section class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-ink">Laporan WhatsApp</h2>
                    <p class="text-xs text-ink-muted">Buat rekap teks untuk bulan {{ $month }}.</p>
                </div>
                <form method="POST" action="{{ route('jadwal-koordinator.whatsapp') }}">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white">Generate Laporan WA</button>
                </form>
            </div>
            @if ($whatsappArtifact['text'] ?? null)
                <p class="mt-3 text-xs text-ink-muted">Dibuat {{ $whatsappArtifact['generated_at'] ?? '-' }}</p>
                <textarea readonly rows="8" class="mt-1 w-full rounded-lg border-border bg-surface text-sm">{{ $whatsappArtifact['text'] }}</textarea>
            @endif
        </section>
    @endif

    <section class="rounded-2xl glass-card p-5">
        <form method="GET" class="grid gap-3 md:grid-cols-5">
            <label class="grid gap-1 text-xs text-ink-muted">Bulan<input type="month" name="month" value="{{ $month }}" class="rounded-lg border-border bg-surface-muted"></label>
            <label class="grid gap-1 text-xs text-ink-muted">Wilayah<select name="wilayah" class="rounded-lg border-border bg-surface-muted"><option value="">Semua Wilayah</option>@foreach ($regionals as $regional)<option @selected(request('wilayah') === $regional)>{{ $regional }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Koordinator<select name="koordinator_name" class="rounded-lg border-border bg-surface-muted"><option value="">Semua Korwil</option>@foreach ($profiles as $regional => $profile)<option value="{{ $profile['name'] }}" @selected(request('koordinator_name') === $profile['name'])>{{ $profile['name'] }}</option>@endforeach</select></label>
            <label class="grid gap-1 text-xs text-ink-muted">Status<select name="status" class="rounded-lg border-border bg-surface-muted"><option value="">Semua Status</option>@foreach (['Rencana', 'Dijadwalkan', 'Selesai', 'Reschedule'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
            <div class="flex items-end gap-2"><button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Terapkan</button><a href="{{ route('jadwal-koordinator') }}" class="rounded-lg border border-border px-3 py-2 text-sm">Reset</a></div>
        </form>
    </section>

    <section class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($regionals as $regional)
            @php $row = $summary[$regional] ?? ['total' => 0, 'Fisik' => 0, 'Zoom' => 0, 'Telepon' => 0, 'Selesai' => 0]; @endphp
            <div class="rounded-2xl glass-card p-4">
                <p class="text-xs text-ink-muted">{{ $regional }}</p>
                <p class="mt-1 text-lg font-semibold text-ink">{{ number_format($row['total'], 0, ',', '.') }} agenda</p>
                <p class="mt-1 text-xs text-ink-muted">{{ number_format($row['Fisik'], 0, ',', '.') }} fisik, {{ number_format($row['Zoom'], 0, ',', '.') }} Zoom, {{ number_format($row['Selesai'], 0, ',', '.') }} selesai</p>
            </div>
        @endforeach
    </section>

    @if ($canManage)
        <section class="mt-5 rounded-2xl glass-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-ink">Tambah Agenda Kunjungan</h2>
                <form method="POST" action="{{ route('jadwal-koordinator.generate') }}" class="flex items-center gap-2">
                    @csrf
                    <input type="hidden" name="month" value="{{ $month }}">
                    <button class="rounded-lg border border-brand-600 px-3 py-2 text-sm font-semibold text-brand-700">Generate {{ $month }}</button>
                </form>
            </div>
            <form method="POST" action="{{ route('jadwal-koordinator.store') }}" class="mt-4 grid gap-3 md:grid-cols-3">
                @csrf
                <input type="date" name="schedule_date" value="{{ now()->format('Y-m-d') }}" required class="rounded-lg border-border bg-surface-muted">
                <select name="wilayah" required class="rounded-lg border-border bg-surface-muted"><option value="">Wilayah</option>@foreach ($regionals as $regional)<option>{{ $regional }}</option>@endforeach</select>
                <select name="koordinator_name" required class="rounded-lg border-border bg-surface-muted">@foreach ($profiles as $regional => $profile)<option value="{{ $profile['name'] }}">{{ $profile['name'] }} - {{ $regional }}</option>@endforeach</select>
                <select name="unit_name" required class="rounded-lg border-border bg-surface-muted"><option value="">Pilih unit/kampus</option>@foreach ($references['campuses'] as $campus)<option value="{{ $campus['label'] }}">{{ $campus['label'] }}</option>@endforeach</select>
                <select name="visit_type" class="rounded-lg border-border bg-surface-muted"><option>Fisik</option><option selected>Zoom</option><option>Telepon</option></select>
                <select name="status" class="rounded-lg border-border bg-surface-muted"><option>Rencana</option><option>Dijadwalkan</option><option>Selesai</option><option>Reschedule</option></select>
                <input name="agenda" value="Monitoring PMB, leads, iklan, dan action plan kampus" required class="md:col-span-3 rounded-lg border-border bg-surface-muted">
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white md:col-span-3">Simpan Jadwal</button>
            </form>
        </section>
    @endif

    <section class="mt-5">
        <h2 class="mb-3 text-base font-semibold text-ink">Jadwal Bulanan — {{ \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->format('F Y') }}</h2>
        @if ($rows->isEmpty())
            <p class="rounded-2xl glass-card p-5 text-center text-sm text-ink-muted">Belum ada jadwal untuk filter ini. Gunakan tombol generate atau tambah agenda manual.</p>
        @endif
        <div class="space-y-4">
            @foreach ($regionals as $regional)
                @php $regionalRows = $grouped[$regional] ?? collect(); @endphp
                @continue($regionalRows->isEmpty())
                <div class="overflow-hidden rounded-2xl glass-card">
                    <div class="flex flex-wrap items-center justify-between gap-2 bg-brand-50/60 px-5 py-3">
                        <div><strong class="text-ink">{{ $regional }}</strong><span class="ml-2 text-xs text-ink-muted">{{ $profiles[$regional]['name'] ?? 'Korwil' }} — domisili {{ $profiles[$regional]['home'] ?? '-' }}</span></div>
                        <b class="text-sm text-ink">{{ number_format($regionalRows->count(), 0, ',', '.') }} agenda</b>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1100px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-border text-xs text-ink-muted">
                                    <th class="py-2 pl-5 pr-3 font-medium">Tanggal</th>
                                    <th class="py-2 pr-3 font-medium">Korwil</th>
                                    <th class="py-2 pr-3 font-medium">Unit/Kampus</th>
                                    <th class="py-2 pr-3 font-medium">Tipe</th>
                                    <th class="py-2 pr-3 font-medium">Agenda</th>
                                    <th class="py-2 pr-3 font-medium">Status</th>
                                    <th class="py-2 pr-3 font-medium">Hasil &amp; Follow Up</th>
                                    <th class="py-2 pr-5 font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($regionalRows as $row)
                                    <tr class="border-b border-border/60 last:border-0">
                                        <td class="py-2 pl-5 pr-3 text-ink"><strong>{{ $row->schedule_date->format('d M Y') }}</strong></td>
                                        <td class="py-2 pr-3 text-ink-muted">{{ $row->koordinator_name }}<br><span class="text-xs">{{ $row->koordinator_home ?: '-' }}</span></td>
                                        <td class="py-2 pr-3 text-ink">{{ $row->unit_name }}</td>
                                        <td class="py-2 pr-3"><span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row->visit_type }}</span></td>
                                        <td class="py-2 pr-3 text-ink-muted">{{ $row->agenda }}</td>
                                        <td class="py-2 pr-3"><span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row->status }}</span></td>
                                        <td class="py-2 pr-3 text-ink-muted">
                                            <span class="block text-ink">{{ $row->result_text ?: '-' }}</span>
                                            <span class="block text-xs">{{ $row->next_action ?: 'Belum ada follow up' }}</span>
                                            @if ($row->attachment_path)
                                                <a href="{{ route('jadwal-koordinator.attachment', $row) }}" target="_blank" rel="noopener" class="text-xs font-medium text-brand-600 underline">Lihat dokumentasi</a>
                                            @endif
                                        </td>
                                        <td class="py-2 pr-5">
                                            @if ($canManage)
                                                <div class="flex flex-col gap-1">
                                                    <details class="text-xs">
                                                        <summary class="cursor-pointer font-medium text-brand-600 underline">Edit</summary>
                                                        <form method="POST" action="{{ route('jadwal-koordinator.update', $row) }}" class="mt-2 grid gap-1">
                                                            @csrf @method('PATCH')
                                                            <input name="unit_name" value="{{ $row->unit_name }}" required class="w-44 rounded border-border px-1 text-xs">
                                                            <select name="visit_type" class="rounded border-border text-xs"><option @selected($row->visit_type === 'Fisik')>Fisik</option><option @selected($row->visit_type === 'Zoom')>Zoom</option><option @selected($row->visit_type === 'Telepon')>Telepon</option></select>
                                                            <input name="agenda" value="{{ $row->agenda }}" required class="w-56 rounded border-border px-1 text-xs">
                                                            <button class="text-left font-medium text-brand-600 underline">Simpan</button>
                                                        </form>
                                                    </details>
                                                    <details class="text-xs">
                                                        <summary class="cursor-pointer font-medium text-brand-600 underline">Laporan</summary>
                                                        <form method="POST" enctype="multipart/form-data" action="{{ route('jadwal-koordinator.report', $row) }}" class="mt-2 grid gap-1">
                                                            @csrf @method('PATCH')
                                                            <select name="status" class="rounded border-border text-xs">@foreach (['Rencana', 'Dijadwalkan', 'Selesai', 'Reschedule'] as $status)<option @selected($row->status === $status)>{{ $status }}</option>@endforeach</select>
                                                            <textarea name="result_text" placeholder="Hasil kunjungan" rows="2" class="w-56 rounded border-border px-1 text-xs">{{ $row->result_text }}</textarea>
                                                            <textarea name="next_action" placeholder="Follow up berikutnya" rows="2" class="w-56 rounded border-border px-1 text-xs">{{ $row->next_action }}</textarea>
                                                            <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf" class="text-xs">
                                                            <button class="text-left font-medium text-brand-600 underline">Simpan Laporan</button>
                                                        </form>
                                                    </details>
                                                    <form method="POST" action="{{ route('jadwal-koordinator.destroy', $row) }}" onsubmit="return confirm('Hapus jadwal ini?')">
                                                        @csrf @method('DELETE')
                                                        <button class="font-medium text-tone-red underline">Hapus</button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-xs text-ink-muted">Lihat saja</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.app>
