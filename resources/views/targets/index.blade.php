<x-layouts.app title="Target & Bobot Scoring" active="scoring">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('scoring') }}" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-ink-muted hover:bg-surface-muted">Hasil Scoring</a>
        <a href="{{ route('scoring.targets') }}" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Target & Bobot</a>
    </div>

    <section class="rounded-2xl glass-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Atur Target & Bobot Indikator</h2>
                <p class="mt-1 text-sm text-ink-muted">Setiap scope bisa punya target dan bobot penilaian sendiri per bulan.</p>
            </div>
            <span class="rounded-lg glass-card-muted px-3 py-1.5 text-xs font-semibold text-ink-muted">{{ $area }}</span>
        </div>

        <form method="POST" action="{{ route('scoring.targets.store') }}" class="mt-4 grid gap-4">
            @csrf
            <div class="grid gap-3 md:grid-cols-3">
                <label class="grid gap-1 text-xs text-ink-muted">Bulan target
                    <input type="month" name="target_month" value="{{ old('target_month', now()->format('Y-m')) }}" required class="rounded-lg border-border bg-surface-muted">
                </label>
                <label class="grid gap-1 text-xs text-ink-muted">Scope
                    <select name="scope_type" class="rounded-lg border-border bg-surface-muted">
                        @foreach(['regional'=>'Regional','wilayah'=>'Wilayah','unit'=>'Unit/Kampus','staff'=>'Staff'] as $key=>$label)
                            <option value="{{ $key }}" @selected(old('scope_type','staff')===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 self-end text-sm text-ink">
                    <input type="checkbox" name="apply_all_scope" value="1" @checked(old('apply_all_scope'))>
                    Terapkan ke semua pilihan scope
                </label>
                <label class="grid gap-1 text-xs text-ink-muted">Wilayah
                    <select name="wilayah" class="rounded-lg border-border bg-surface-muted">
                        <option value="">Pilih wilayah</option>
                        @foreach($references['regionals'] as $value)
                            <option @selected(old('wilayah')===$value)>{{ $value }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-xs text-ink-muted">Unit/Kampus
                    <select name="unit_name" class="rounded-lg border-border bg-surface-muted">
                        <option value="">Pilih unit</option>
                        @foreach($references['campuses'] as $value)
                            <option @selected(old('unit_name')===$value)>{{ $value }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1 text-xs text-ink-muted">Staff
                    <select name="staff_name" class="rounded-lg border-border bg-surface-muted">
                        <option value="">Pilih staff</option>
                        @foreach($references['staff'] as $row)
                            <option @selected(old('staff_name')===$row->name)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="overflow-x-auto rounded-xl border border-border">
                <table class="w-full min-w-[780px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border bg-surface-muted/60 text-xs text-ink-muted">
                            <th class="px-3 py-2">Kelompok</th>
                            <th class="px-3 py-2">Indikator</th>
                            <th class="px-3 py-2">Target Bulanan</th>
                            <th class="px-3 py-2">Bobot (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($indicators as $key => $indicator)
                            @php
                                $targetOld = old("indicator_targets.$key.target", 0);
                                $weightOld = old("indicator_targets.$key.weight", $indicator['default_weight']);
                            @endphp
                            <tr class="border-b border-border/60">
                                <td class="px-3 py-2 text-xs font-semibold text-ink-muted">{{ $indicator['group'] }}</td>
                                <td class="px-3 py-2 font-semibold text-ink">{{ $indicator['label'] }}</td>
                                <td class="px-3 py-2">
                                    <input type="number" min="0" step="{{ $key === 'realisasi_iklan' ? '0.01' : '1' }}" name="indicator_targets[{{ $key }}][target]" value="{{ $targetOld }}" class="w-full rounded-lg border-border bg-surface-muted">
                                </td>
                                <td class="px-3 py-2">
                                    <input type="number" min="0" max="100" step="0.01" name="indicator_targets[{{ $key }}][weight]" value="{{ $weightOld }}" class="w-full rounded-lg border-border bg-surface-muted">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <label class="grid gap-1 text-xs text-ink-muted md:col-span-2">Catatan
                    <textarea name="notes" rows="2" class="rounded-lg border-border bg-surface-muted">{{ old('notes') }}</textarea>
                </label>
                <div class="flex items-end">
                    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Simpan target</button>
                </div>
            </div>
        </form>
    </section>

    <section class="mt-5 overflow-x-auto rounded-2xl glass-card p-5">
        <h2 class="mb-3 text-base font-semibold text-ink">Target staff terbaru</h2>
        <table class="w-full min-w-[1080px] text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs text-ink-muted">
                    <th class="py-2 pr-3">Bulan</th>
                    <th class="py-2 pr-3">Wilayah</th>
                    <th class="py-2 pr-3">Unit</th>
                    <th class="py-2 pr-3">Staff</th>
                    <th class="py-2 pr-3">Reg</th>
                    <th class="py-2 pr-3">Herreg</th>
                    <th class="py-2 pr-3">Leads</th>
                    <th class="py-2 pr-3">FU</th>
                    <th class="py-2 pr-3">Bobot</th>
                    <th class="py-2">Indikator Terisi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($targets as $target)
                    @php
                        $indicatorRows = is_array($target->indicator_targets) ? $target->indicator_targets : [];
                        $weightTotal = collect($indicatorRows)->sum(fn ($row) => (float) ($row['weight'] ?? 0));
                        $filledCount = collect($indicatorRows)->filter(fn ($row) => (float) ($row['target'] ?? 0) > 0 || (float) ($row['weight'] ?? 0) > 0)->count();
                    @endphp
                    <tr class="border-b border-border/60">
                        <td class="py-2 pr-3">{{ $target->target_month }}</td>
                        <td class="py-2 pr-3">{{ $target->wilayah ?: '-' }}</td>
                        <td class="py-2 pr-3">{{ $target->unit_name ?: '-' }}</td>
                        <td class="py-2 pr-3">{{ $target->staff_name ?: '-' }}</td>
                        <td class="py-2 pr-3">{{ number_format((float) $target->target_registrasi, 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">{{ number_format((float) $target->target_herregistrasi, 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">{{ number_format((float) $target->target_leads, 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">{{ number_format((float) $target->target_follow_up, 0, ',', '.') }}</td>
                        <td class="py-2 pr-3">{{ number_format($weightTotal, 2, ',', '.') }}%</td>
                        <td class="py-2">{{ $filledCount }} indikator</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="py-8 text-center text-ink-muted">Belum ada target staff.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-layouts.app>
