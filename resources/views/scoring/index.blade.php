<x-layouts.app title="Scoring" active="scoring">
    @php $user = auth()->user(); @endphp
    <form method="GET" action="{{ route('scoring') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl glass-card p-4">
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Dari tanggal</label>
            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Sampai tanggal</label>
            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
        </div>
        @unless ($user->role === 'koordinator')
            <div>
                <label class="mb-1 block text-xs font-medium text-ink-muted">Wilayah</label>
                <select name="wilayah" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
                    <option value="">Semua Wilayah</option>
                    @foreach ($referenceOptions['regionals'] as $regional)
                        <option value="{{ $regional }}" @selected($filters['wilayah'] === $regional)>{{ $regional }}</option>
                    @endforeach
                </select>
            </div>
        @endunless
        <div class="ml-auto flex gap-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Terapkan</button>
            <a href="{{ route('scoring') }}" class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-ink-muted hover:bg-surface-muted">Reset</a>
        </div>
    </form>

    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('scoring') }}" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Hasil Scoring</a>
        @if (\App\Support\RsmRole::canManageTargets($user))
            <a href="{{ route('scoring.targets') }}" class="rounded-lg border border-border px-3 py-2 text-sm font-semibold text-ink-muted hover:bg-surface-muted">Target & Bobot</a>
        @endif
    </div>

    <section class="rounded-2xl glass-card p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-ink">Scoring - Semua Indikator Penilaian</h2>
                <p class="mt-1 text-xs text-ink-muted">Kumpulan indikator mentah per staff berdasarkan konfigurasi indikator scoring.</p>
            </div>
            @if ($syncedAt)
                <span class="text-xs text-ink-muted">Sumber Collab tersinkron: {{ \Illuminate\Support\Carbon::parse($syncedAt)->format('d M Y') }}</span>
            @endif
        </div>

        @if (empty($rows))
            <p class="py-6 text-center text-sm text-ink-muted">Belum ada data pada periode/filter ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-[760px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs leading-tight text-ink-muted">
                            <th class="w-56 py-2 pr-3 font-medium">Staff</th>
                            @foreach ($indicators as $indicator)
                                <th class="w-16 py-2 pr-3 text-center font-medium">{{ $indicator['label'] ?? '-' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-border/60">
                                <td class="py-2 pr-3 font-medium text-ink whitespace-nowrap" title="{{ $row['wilayah'] }} - {{ $row['unit_name'] }}">{{ $row['name'] }}</td>
                                @foreach ($indicators as $indicator)
                                    @php($metricKey = (string) ($indicator['metric_key'] ?? ''))
                                    <td class="py-2 pr-3 text-center text-ink">{{ number_format((float) ($row[$metricKey] ?? 0), 0, ',', '.') }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
