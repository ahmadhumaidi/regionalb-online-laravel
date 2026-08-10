@php $isSeniorExpenseForm = ! $editing && $config['label'] === 'anggaran' && $user->role === 'super_user'; @endphp
<x-layouts.app :title="$isSeniorExpenseForm ? 'Tambah Pengeluaran Senior Manager' : ($editing ? 'Edit ' : 'Tambah ') . $config['title']" :active="$active">
    <section class="rounded-2xl glass-card p-5">
        <div class="mb-5 flex items-start justify-between gap-3"><div><h2 class="text-base font-semibold text-ink">{{ $isSeniorExpenseForm ? 'Tambah Pengeluaran Senior Manager' : ($editing ? 'Edit' : 'Tambah') . ' ' . $config['title'] }}</h2><p class="mt-1 text-sm text-ink-muted">Lengkapi data laporan.</p></div><a href="{{ route($active) }}" class="rounded-lg border border-border px-3 py-2 text-sm">Kembali</a></div>
        <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('reports.update', $report) : route($storeRoute) }}" class="grid gap-4 md:grid-cols-2">
            @csrf @if ($editing) @method('PATCH') @endif
            @if ($errors->any())<div class="md:col-span-2 rounded-lg border border-tone-red/30 bg-tone-red/10 p-3 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @if ($config['label'] === 'anggaran' && $editing)
                @php $adsFields = \App\Services\Reports\ReportFormService::adsEditFieldsForRole($user->role); @endphp
                @if (in_array('report_date', $adsFields))
                    <label class="grid gap-1 text-sm">Tanggal laporan<input type="date" name="report_date" required value="{{ old('report_date', optional($report->report_date)->format('Y-m-d') ?: now()->toDateString()) }}" class="rounded-lg border-border bg-surface-muted"></label>
                @endif
                @if (in_array('ad_period', $adsFields))
                    @php $adPeriodValue = old('ad_period', $report->ad_period ?: \App\Services\AdBudget\AdBudgetPeriods::default()); @endphp
                    <label class="grid gap-1 text-sm">Periode iklan<select name="ad_period" required class="rounded-lg border-border bg-surface-muted"><option value="">Pilih periode</option>@foreach (\App\Services\AdBudget\AdBudgetPeriods::options() as $option)<option value="{{ $option['value'] }}" @selected($adPeriodValue === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                @endif
                @if (in_array('wilayah', $adsFields))
                    <label class="grid gap-1 text-sm">Wilayah<select name="wilayah" class="rounded-lg border-border bg-surface-muted"><option value="">Pilih wilayah</option>@foreach ($references['regionals'] as $option)<option value="{{ $option }}" @selected(old('wilayah', $report->wilayah) === $option)>{{ $option }}</option>@endforeach</select></label>
                @endif
                @if (in_array('unit_name', $adsFields))
                    @php $unitValue = old('unit_name', $report->unit_name); $unitLabels = collect($references['campuses'])->pluck('label'); @endphp
                    <label class="grid gap-1 text-sm">Unit/Kampus<select name="unit_name" class="rounded-lg border-border bg-surface-muted"><option value="">Pilih unit/kampus</option>@if ($unitValue && ! $unitLabels->contains($unitValue))<option value="{{ $unitValue }}" selected>{{ $unitValue }}</option>@endif @foreach ($unitLabels as $option)<option value="{{ $option }}" @selected($unitValue === $option)>{{ $option }}</option>@endforeach</select></label>
                @endif
                @if (in_array('platform', $adsFields))
                    <label class="grid gap-1 text-sm">Platform<select name="platform" required class="rounded-lg border-border bg-surface-muted"><option value="">Pilih platform</option>@foreach ($config['options'] as $option)<option @selected(old('platform', $report->platform) === $option)>{{ $option }}</option>@endforeach</select></label>
                @endif
                @if (in_array('campaign_name', $adsFields))
                    <label class="grid gap-1 text-sm">Nama campaign<input name="campaign_name" required value="{{ old('campaign_name', $report->campaign_name) }}" class="rounded-lg border-border bg-surface-muted"></label>
                @endif
                @if (in_array('ad_goal', $adsFields))
                    <label class="grid gap-1 text-sm">Tujuan iklan<select name="ad_goal" class="rounded-lg border-border bg-surface-muted">@foreach (['Leads','Awareness','Traffic','Conversion'] as $option)<option @selected(old('ad_goal', $report->ad_goal ?: 'Leads') === $option)>{{ $option }}</option>@endforeach</select></label>
                @endif
                @if (in_array('budget_requested', $adsFields))
                    <label class="grid gap-1 text-sm">Anggaran diajukan<input type="number" min="0.01" step="0.01" name="budget_requested" required value="{{ old('budget_requested', $report->budget_requested) }}" class="rounded-lg border-border bg-surface-muted"></label>
                @endif
                @if (in_array('budget_approved', $adsFields))
                    <label class="grid gap-1 text-sm">Anggaran disetujui<input type="number" min="0" step="0.01" name="budget_approved" value="{{ old('budget_approved', $report->budget_approved) }}" class="rounded-lg border-border bg-surface-muted"></label>
                @endif
                @if (in_array('realization_amount', $adsFields))
                    <label class="grid gap-1 text-sm">Realisasi pemakaian<input type="number" min="0" step="0.01" name="realization_amount" value="{{ old('realization_amount', $report->realization_amount) }}" class="rounded-lg border-border bg-surface-muted"></label>
                @endif
                @if (in_array('cpl', $adsFields))
                    <label class="grid gap-1 text-sm">CPL / Cost per Lead<input type="text" readonly value="{{ number_format((float) $report->cpl, 2, ',', '.') }}" title="Otomatis dihitung: realisasi ÷ jumlah data hasil iklan yang sudah diupload" class="w-full max-w-full rounded-lg glass-card-muted bg-surface-muted text-ink-muted"><span class="text-xs text-ink-muted">Otomatis: realisasi ÷ jumlah data hasil iklan yang diupload.</span></label>
                @endif
                @if (in_array('campaign_link', $adsFields))
                    <label class="grid gap-1 text-sm">Link campaign<input name="campaign_link" value="{{ old('campaign_link', $report->campaign_link) }}" class="rounded-lg border-border bg-surface-muted"></label>
                @endif
                @if (array_intersect(['attachment_path', 'insight_attachment_path', 'ad_leads_file'], $adsFields))
                    <div class="md:col-span-2 mt-2 border-t border-border pt-4"><p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Lampiran & Data Pendukung</p></div>
                @endif
                @if (in_array('attachment_path', $adsFields))
                    <div class="md:col-span-2 rounded-xl border border-l-4 border-border border-l-tone-blue bg-surface-muted/50 p-4">
                        <label class="grid gap-1 text-sm"><span class="flex items-center gap-1.5 font-semibold text-ink"><x-icon name="document" class="h-4 w-4 text-tone-blue" />Upload bukti invoice/screenshot</span><input type="file" name="attachment_path" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full max-w-full rounded-lg border-border bg-surface-muted"><span class="text-xs text-ink-muted">JPG, PNG, WEBP, atau PDF; maksimal 5 MB.</span></label>
                        @if ($report->attachment_path)<p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-ink-muted"><a href="{{ route('reports.attachment', $report) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted"><x-icon name="eye" class="h-3.5 w-3.5" />Lihat bukti saat ini</a> Kosongkan jika tidak ingin mengganti.</p>@endif
                    </div>
                @endif
                @if (in_array('insight_attachment_path', $adsFields))
                    <div class="md:col-span-2 rounded-xl border border-l-4 border-border border-l-tone-orange bg-surface-muted/50 p-4">
                        <label class="grid gap-1 text-sm"><span class="flex items-center gap-1.5 font-semibold text-ink"><x-icon name="bolt" class="h-4 w-4 text-tone-orange" />Upload bukti insight/pengeluaran iklan</span><input type="file" name="insight_attachment_path" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full max-w-full rounded-lg border-border bg-surface-muted"><span class="text-xs text-ink-muted">Screenshot insight/spend dari platform iklan; JPG, PNG, WEBP, atau PDF, maksimal 5 MB.</span></label>
                        @if ($report->insight_attachment_path)<p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-ink-muted"><a href="{{ route('reports.insight-attachment', $report) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted"><x-icon name="eye" class="h-3.5 w-3.5" />Lihat bukti insight saat ini</a> Kosongkan jika tidak ingin mengganti.</p>@endif
                    </div>
                @endif
                @if (in_array('ad_leads_file', $adsFields))
                    <div class="md:col-span-2 rounded-xl border border-l-4 border-border border-l-tone-green bg-surface-muted/50 p-4">
                        <label class="grid gap-1 text-sm"><span class="flex items-center gap-1.5 font-semibold text-ink"><x-icon name="chart-bar" class="h-4 w-4 text-tone-green" />Upload data hasil iklan (.xls/.xlsx)</span><div class="flex flex-wrap items-center gap-2"><input type="file" name="ad_leads_file" accept=".xls,.xlsx" class="w-full min-w-0 flex-1 rounded-lg border-border bg-surface-muted"><a href="{{ route('anggaran.leads.template') }}" class="shrink-0 whitespace-nowrap rounded-lg border border-border px-3 py-2 text-sm">Download Template</a></div><span class="text-xs text-ink-muted">Kosongkan jika tidak ingin menambah data hasil iklan.</span></label>
                    </div>
                @endif
                @if (in_array('notes', $adsFields))
                    <label class="grid gap-1 text-sm md:col-span-2">Catatan performa iklan<textarea name="notes" rows="2" class="rounded-lg border-border bg-surface-muted">{{ old('notes', $report->notes) }}</textarea></label>
                @endif
                @if (\App\Support\RsmRole::canVerifyAdBudgetRequest($report, $user) && $report->status === 'Dilaporkan Unit')
                    <label class="md:col-span-2 flex items-center gap-2 rounded-xl border border-l-4 border-border border-l-tone-blue-light bg-surface-muted/50 p-4 text-sm font-semibold text-ink">
                        <input type="checkbox" name="mark_verified" value="1" class="h-4 w-4 rounded border-border">
                        Verifikasi laporan ini setelah disimpan
                    </label>
                @endif
                @if (\App\Support\RsmRole::canManageAdBudget($user) && $report->status === 'Diverifikasi')
                    <label class="md:col-span-2 flex items-center gap-2 rounded-xl border border-l-4 border-border border-l-tone-purple bg-surface-muted/50 p-4 text-sm font-semibold text-ink">
                        <input type="checkbox" name="mark_selesai" value="1" class="h-4 w-4 rounded border-border">
                        Tandai laporan ini selesai setelah disimpan
                    </label>
                @endif
                <div class="md:col-span-2"><button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Simpan Perubahan</button></div>
            @else
                <label class="grid gap-1 text-sm">Tanggal laporan<input type="date" name="report_date" required value="{{ old('report_date', optional($report->report_date)->format('Y-m-d') ?: now()->toDateString()) }}" class="rounded-lg border-border bg-surface-muted"></label>
                @if ($config['label'] === 'anggaran')
                    @php $adPeriodValue = old('ad_period', $report->ad_period ?: \App\Services\AdBudget\AdBudgetPeriods::default()); @endphp
                    <label class="grid gap-1 text-sm">Periode iklan<select name="ad_period" required class="rounded-lg border-border bg-surface-muted"><option value="">Pilih periode</option>@foreach (\App\Services\AdBudget\AdBudgetPeriods::options() as $option)<option value="{{ $option['value'] }}" @selected($adPeriodValue === $option['value'])>{{ $option['label'] }}</option>@endforeach</select></label>
                @endif
                @unless ($config['label'] === 'anggaran' && $user->role === 'super_user')
                    @foreach (['wilayah' => 'Wilayah', 'unit_name' => 'Unit/Kampus', 'staff_name' => 'Nama staff'] as $field => $label)
                        @php $locked = $user->role === 'staff'; $options = $field === 'wilayah' ? $references['regionals'] : ($field === 'unit_name' ? collect($references['campuses'])->pluck('label')->all() : collect($references['staff'])->pluck('name')->all()); $value = old($field, $report->{$field}); if ($locked) { $value = old($field, $field === 'wilayah' ? $user->regional : ($field === 'unit_name' ? $user->campus_name : $user->name)); } @endphp
                        <label class="grid gap-1 text-sm">{{ $label }} @if ($locked)<input readonly value="{{ $value }}" class="rounded-lg border-border bg-surface-muted"><input type="hidden" name="{{ $field }}" value="{{ $value }}">@else<select name="{{ $field }}" class="rounded-lg border-border bg-surface-muted"><option value="">Pilih {{ strtolower($label) }}</option>@if ($value && ! in_array($value, $options, true))<option value="{{ $value }}" selected>{{ $value }}</option>@endif @foreach ($options as $option)<option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>@endforeach</select>@endif</label>
                    @endforeach
                @endunless
                @if ($config['label'] === 'anggaran')
                    <label class="grid gap-1 text-sm">Platform<select name="platform" required class="rounded-lg border-border bg-surface-muted"><option value="">Pilih platform</option>@foreach ($config['options'] as $option)<option @selected(old('platform', $report->platform) === $option)>{{ $option }}</option>@endforeach</select></label>
                    <label class="grid gap-1 text-sm">Nama campaign<input name="campaign_name" required value="{{ old('campaign_name', $report->campaign_name) }}" class="rounded-lg border-border bg-surface-muted"></label>
                    <label class="grid gap-1 text-sm">Tujuan iklan<select name="ad_goal" class="rounded-lg border-border bg-surface-muted">@foreach (['Leads','Awareness','Traffic','Conversion'] as $option)<option @selected(old('ad_goal', $report->ad_goal ?: 'Leads') === $option)>{{ $option }}</option>@endforeach</select></label>
                    <label class="grid gap-1 text-sm">Anggaran<input type="number" min="0.01" step="0.01" name="budget_requested" required value="{{ old('budget_requested', $report->budget_requested) }}" class="rounded-lg border-border bg-surface-muted"></label>
                @elseif ($config['label'] === 'kegiatan')
                    <label class="grid gap-1 text-sm">Jenis kegiatan<select name="activity_kind" class="rounded-lg border-border bg-surface-muted">@foreach ($config['options'] as $option)<option @selected(old('activity_kind', $report->activity_kind) === $option)>{{ $option }}</option>@endforeach</select></label>
                    <label class="grid gap-1 text-sm">Nama kegiatan<input name="title" required value="{{ old('title', $report->title) }}" class="rounded-lg border-border bg-surface-muted"></label>
                    <label class="grid gap-1 text-sm">Lokasi<input name="location_name" value="{{ old('location_name', $report->location_name) }}" class="rounded-lg border-border bg-surface-muted"></label><label class="grid gap-1 text-sm">Leads<input type="number" min="0" name="leads_count" value="{{ old('leads_count', $report->leads_count ?: 0) }}" class="rounded-lg border-border bg-surface-muted"></label>
                    <label class="grid gap-1 text-sm md:col-span-2">Target<textarea name="target_text" rows="2" class="rounded-lg border-border bg-surface-muted">{{ old('target_text', $report->target_text) }}</textarea></label><label class="grid gap-1 text-sm md:col-span-2">Hasil<textarea name="result_text" rows="3" class="rounded-lg border-border bg-surface-muted">{{ old('result_text', $report->result_text) }}</textarea></label><label class="grid gap-1 text-sm md:col-span-2">Catatan<textarea name="notes" rows="2" class="rounded-lg border-border bg-surface-muted">{{ old('notes', $report->notes) }}</textarea></label>
                @else
                    <label class="grid gap-1 text-sm">Kategori<select name="category" class="rounded-lg border-border bg-surface-muted">@foreach ($config['options'] as $option)<option @selected(old('category', $report->category) === $option)>{{ $option }}</option>@endforeach</select></label><label class="grid gap-1 text-sm">Deskripsi<input name="title" required value="{{ old('title', $report->title) }}" class="rounded-lg border-border bg-surface-muted"></label>
                    <label class="grid gap-1 text-sm md:col-span-2">Hasil<textarea name="result_text" rows="3" class="rounded-lg border-border bg-surface-muted">{{ old('result_text', $report->result_text) }}</textarea></label><label class="grid gap-1 text-sm">Kendala<textarea name="obstacle_text" rows="3" class="rounded-lg border-border bg-surface-muted">{{ old('obstacle_text', $report->obstacle_text) }}</textarea></label><label class="grid gap-1 text-sm">Tindak lanjut<textarea name="follow_up_text" rows="3" class="rounded-lg border-border bg-surface-muted">{{ old('follow_up_text', $report->follow_up_text) }}</textarea></label>
                @endif
                <label class="grid gap-1 text-sm md:col-span-2">Lampiran<input type="file" name="attachment_path" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full max-w-full rounded-lg border-border bg-surface-muted"><span class="text-xs text-ink-muted">JPG, PNG, WEBP, atau PDF; maksimal 5 MB.</span></label>
                @if ($editing && $report->attachment_path)<p class="md:col-span-2"><a href="{{ route('reports.attachment', $report) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-border px-3 py-1.5 text-xs font-semibold text-ink hover:bg-surface-muted"><x-icon name="eye" class="h-3.5 w-3.5" />Lihat lampiran saat ini</a></p>@endif
                @if ($config['label'] !== 'anggaran')<label class="grid gap-1 text-sm">Status<select name="status" class="rounded-lg border-border bg-surface-muted">@foreach ($config['statuses'] as $status)<option @selected(old('status', $report->status ?: 'Draft') === $status)>{{ $status }}</option>@endforeach</select></label>@endif
                <div class="md:col-span-2"><button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">{{ $isSeniorExpenseForm ? 'Simpan Pengeluaran' : ($config['label'] === 'anggaran' ? 'Ajukan Iklan' : ($editing ? 'Simpan Perubahan' : 'Simpan Laporan')) }}</button></div>
            @endif
        </form>
    </section>
</x-layouts.app>
