<x-layouts.app title="CRM Leads" active="crm">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl glass-card p-4">
            <p class="text-xs text-ink-muted">Total Lead</p>
            <p class="mt-1 text-lg font-semibold text-ink">{{ number_format($summary['total'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl glass-card p-4">
            <p class="text-xs text-ink-muted">Dari CTWA</p>
            <p class="mt-1 text-lg font-semibold text-ink">{{ number_format($summary['ctwa'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl glass-card p-4">
            <p class="text-xs text-ink-muted">Lead Baru</p>
            <p class="mt-1 text-lg font-semibold text-ink">{{ number_format($summary['baru'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-2xl glass-card p-4">
            <p class="text-xs text-ink-muted">Closing</p>
            <p class="mt-1 text-lg font-semibold text-ink">{{ number_format($summary['closing'], 0, ',', '.') }}</p>
        </div>
    </section>

    <section class="mt-5 rounded-2xl glass-card p-5">
        <form method="GET" class="grid gap-3 md:grid-cols-4">
            <label class="grid gap-1 text-xs text-ink-muted">Status
                <select name="status" class="rounded-lg border-border bg-surface-muted">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $status)
                        <option @selected(request('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-xs text-ink-muted">Sumber
                <select name="source" class="rounded-lg border-border bg-surface-muted">
                    <option value="">Semua Sumber</option>
                    @foreach ($sources as $source)
                        <option @selected(request('source') === $source)>{{ $source }}</option>
                    @endforeach
                </select>
            </label>
            @if ($user->role !== 'staff')
                <label class="grid gap-1 text-xs text-ink-muted">Wilayah
                    <select name="wilayah" class="rounded-lg border-border bg-surface-muted">
                        <option value="">Semua Wilayah</option>
                        @foreach ($regionals as $regional)
                            <option @selected(request('wilayah') === $regional)>{{ $regional }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <div class="flex items-end gap-2">
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Terapkan</button>
                <a href="{{ route('crm') }}" class="rounded-lg border border-border px-3 py-2 text-sm">Reset</a>
            </div>
        </form>
        @if ($isFullAccess)
            <div class="mt-3">
                <a href="{{ route('crm', ['unassigned' => 1]) }}" class="inline-flex items-center gap-1 rounded-full {{ request()->boolean('unassigned') ? 'bg-brand-600 text-white' : 'bg-surface-muted text-ink-muted' }} px-3 py-1 text-xs font-medium">Belum Ditugaskan</a>
            </div>
        @endif
    </section>

    <section class="mt-5 rounded-2xl glass-card p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-ink">Tambah Lead</h2>
            <button type="button" onclick="document.getElementById('crm-create-dialog').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">+ Tambah Lead</button>
        </div>
    </section>

    <dialog id="crm-create-dialog" class="schedule-dialog" onclick="if (event.target === this) this.close()">
        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-border bg-surface p-5 shadow-2xl">
            <div class="mb-4 flex items-start justify-between gap-3">
                <h2 class="text-base font-semibold text-ink">Tambah Lead</h2>
                <button type="button" onclick="this.closest('dialog').close()" class="rounded-md border border-border px-2 py-1 text-xs text-ink-muted hover:text-ink">Tutup</button>
            </div>
            <form method="POST" action="{{ route('crm.store') }}" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <label class="grid gap-1 text-sm sm:col-span-2">Nama Lead<input name="lead_name" required class="rounded-lg border-border bg-surface-muted"></label>
                <label class="grid gap-1 text-sm">WhatsApp<input name="whatsapp" class="rounded-lg border-border bg-surface-muted"></label>
                <label class="grid gap-1 text-sm">Email<input type="email" name="email" class="rounded-lg border-border bg-surface-muted"></label>
                <label class="grid gap-1 text-sm">Kampus Tujuan<input name="campus_name" class="rounded-lg border-border bg-surface-muted"></label>
                <label class="grid gap-1 text-sm">Jurusan<input name="major_name" class="rounded-lg border-border bg-surface-muted"></label>
                <label class="grid gap-1 text-sm">Asal Kota<input name="origin_city" class="rounded-lg border-border bg-surface-muted"></label>
                <label class="grid gap-1 text-sm">Sumber<select name="source" required class="rounded-lg border-border bg-surface-muted">@foreach ($sources as $source)<option>{{ $source }}</option>@endforeach</select></label>
                @if ($user->role !== 'staff')
                    <label class="grid gap-1 text-sm">Wilayah<select name="wilayah" class="rounded-lg border-border bg-surface-muted">@foreach ($regionals as $regional)<option @selected($user->regional === $regional)>{{ $regional }}</option>@endforeach</select></label>
                @endif
                <label class="grid gap-1 text-sm sm:col-span-2">Catatan<textarea name="notes" rows="2" class="rounded-lg border-border bg-surface-muted"></textarea></label>
                <div class="flex justify-end gap-2 pt-1 sm:col-span-2">
                    <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg border border-border px-3 py-2 text-sm">Batal</button>
                    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Simpan Lead</button>
                </div>
            </form>
        </div>
    </dialog>

    <section class="mt-5">
        <h2 class="mb-3 text-base font-semibold text-ink">Daftar Lead</h2>
        @if ($rows->isEmpty())
            <p class="rounded-2xl glass-card p-5 text-center text-sm text-ink-muted">Belum ada lead untuk filter ini.</p>
        @else
            <div class="overflow-hidden rounded-2xl glass-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-border text-xs text-ink-muted">
                                <th class="py-2 pl-5 pr-3 font-medium">Tanggal</th>
                                <th class="py-2 pr-3 font-medium">Lead</th>
                                <th class="py-2 pr-3 font-medium">Kampus</th>
                                <th class="py-2 pr-3 font-medium">Sumber</th>
                                <th class="py-2 pr-3 font-medium">Status</th>
                                <th class="py-2 pr-3 font-medium">Pemilik</th>
                                <th class="py-2 pr-3 font-medium">Follow Up</th>
                                <th class="py-2 pr-5 font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-b border-border/60 last:border-0">
                                    <td class="py-2 pl-5 pr-3 text-ink-muted">{{ $row->created_at->format('d M Y') }}</td>
                                    <td class="py-2 pr-3 text-ink">
                                        <strong>{{ $row->lead_name }}</strong>
                                        <span class="block text-xs text-ink-muted">{{ $row->whatsapp ?: '-' }}</span>
                                    </td>
                                    <td class="py-2 pr-3 text-ink-muted">{{ $row->campus_name ?: '-' }}</td>
                                    <td class="py-2 pr-3"><span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row->source }}</span></td>
                                    <td class="py-2 pr-3"><span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] font-medium text-ink-muted">{{ $row->status }}</span></td>
                                    <td class="py-2 pr-3 text-ink-muted">
                                        @if ($row->owner_user_id)
                                            {{ $row->owner->name ?? $row->created_by_name ?? '-' }}
                                        @else
                                            <span class="rounded-full bg-tone-amber/15 px-2 py-0.5 text-[11px] font-medium text-tone-amber">Belum ditugaskan</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3 text-ink-muted">{{ $row->follow_up_result ?: '-' }}</td>
                                    <td class="py-2 pr-5">
                                        @if (\App\Services\Crm\CrmLeadScope::canManage($user, (string) $row->wilayah, $row->owner_user_id))
                                            <div class="flex flex-col items-start gap-1">
                                                <button type="button" onclick="document.getElementById('crm-edit-{{ $row->id }}').showModal()" class="rounded-md border border-border px-2 py-1 text-xs font-medium text-ink hover:bg-surface-muted">Edit</button>
                                                <button type="button" onclick="document.getElementById('crm-status-{{ $row->id }}').showModal()" class="rounded-md border border-brand-600 px-2 py-1 text-xs font-medium text-brand-700 hover:bg-brand-50">Update Status</button>
                                                <form method="POST" action="{{ route('crm.destroy', $row) }}" onsubmit="return confirm('Hapus lead ini?')">
                                                    @csrf @method('DELETE')
                                                    <button class="rounded-md border border-tone-red px-2 py-1 text-xs font-medium text-tone-red hover:bg-tone-red/10">Hapus</button>
                                                </form>
                                            </div>

                                            <dialog id="crm-edit-{{ $row->id }}" class="schedule-dialog" onclick="if (event.target === this) this.close()">
                                                <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-border bg-surface p-5 shadow-2xl">
                                                    <div class="mb-4 flex items-start justify-between gap-3">
                                                        <h2 class="text-base font-semibold text-ink">Edit Lead</h2>
                                                        <button type="button" onclick="this.closest('dialog').close()" class="rounded-md border border-border px-2 py-1 text-xs text-ink-muted hover:text-ink">Tutup</button>
                                                    </div>
                                                    <form method="POST" action="{{ route('crm.update', $row) }}" class="grid gap-3 sm:grid-cols-2">
                                                        @csrf @method('PATCH')
                                                        <label class="grid gap-1 text-sm sm:col-span-2">Nama Lead<input name="lead_name" value="{{ $row->lead_name }}" required class="rounded-lg border-border bg-surface-muted"></label>
                                                        <label class="grid gap-1 text-sm">WhatsApp<input name="whatsapp" value="{{ $row->whatsapp }}" class="rounded-lg border-border bg-surface-muted"></label>
                                                        <label class="grid gap-1 text-sm">Email<input type="email" name="email" value="{{ $row->email }}" class="rounded-lg border-border bg-surface-muted"></label>
                                                        <label class="grid gap-1 text-sm">Kampus Tujuan<input name="campus_name" value="{{ $row->campus_name }}" class="rounded-lg border-border bg-surface-muted"></label>
                                                        <label class="grid gap-1 text-sm">Jurusan<input name="major_name" value="{{ $row->major_name }}" class="rounded-lg border-border bg-surface-muted"></label>
                                                        <label class="grid gap-1 text-sm">Asal Kota<input name="origin_city" value="{{ $row->origin_city }}" class="rounded-lg border-border bg-surface-muted"></label>
                                                        <label class="grid gap-1 text-sm">Sumber<select name="source" required class="rounded-lg border-border bg-surface-muted">@foreach ($sources as $source)<option @selected($row->source === $source)>{{ $source }}</option>@endforeach</select></label>
                                                        @if ($isFullAccess)
                                                            <label class="grid gap-1 text-sm">Wilayah<select name="wilayah" class="rounded-lg border-border bg-surface-muted"><option value="">Belum ditugaskan</option>@foreach ($regionals as $regional)<option value="{{ $regional }}" @selected($row->wilayah === $regional)>{{ $regional }}</option>@endforeach</select></label>
                                                            <label class="grid gap-1 text-sm">Assign ke Staff<select name="owner_user_id" class="rounded-lg border-border bg-surface-muted"><option value="">Belum ditugaskan</option>@foreach ($staffOptions as $staffOption)<option value="{{ $staffOption->id }}" @selected($row->owner_user_id === $staffOption->id)>{{ $staffOption->name }} ({{ $staffOption->regional }})</option>@endforeach</select></label>
                                                        @endif
                                                        <label class="grid gap-1 text-sm sm:col-span-2">Catatan<textarea name="notes" rows="2" class="rounded-lg border-border bg-surface-muted">{{ $row->notes }}</textarea></label>
                                                        <div class="flex justify-end gap-2 pt-1 sm:col-span-2">
                                                            <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg border border-border px-3 py-2 text-sm">Batal</button>
                                                            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Simpan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </dialog>

                                            <dialog id="crm-status-{{ $row->id }}" class="schedule-dialog" onclick="if (event.target === this) this.close()">
                                                <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-border bg-surface p-5 shadow-2xl">
                                                    <div class="mb-4 flex items-start justify-between gap-3">
                                                        <h2 class="text-base font-semibold text-ink">Update Status — {{ $row->lead_name }}</h2>
                                                        <button type="button" onclick="this.closest('dialog').close()" class="rounded-md border border-border px-2 py-1 text-xs text-ink-muted hover:text-ink">Tutup</button>
                                                    </div>
                                                    <form method="POST" action="{{ route('crm.status', $row) }}" class="grid gap-3">
                                                        @csrf @method('PATCH')
                                                        <label class="grid gap-1 text-sm">Status<select name="status" class="rounded-lg border-border bg-surface-muted">@foreach ($statuses as $status)<option @selected($row->status === $status)>{{ $status }}</option>@endforeach</select></label>
                                                        <label class="grid gap-1 text-sm">Hasil Follow Up<textarea name="follow_up_result" rows="3" class="rounded-lg border-border bg-surface-muted">{{ $row->follow_up_result }}</textarea></label>
                                                        <div class="flex justify-end gap-2 pt-1">
                                                            <button type="button" onclick="this.closest('dialog').close()" class="rounded-lg border border-border px-3 py-2 text-sm">Batal</button>
                                                            <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Simpan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </dialog>
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
        @endif
    </section>
</x-layouts.app>
