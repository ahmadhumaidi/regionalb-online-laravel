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

    <section class="rounded-2xl glass-card p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-ink">Scoring — Semua Indikator Penilaian</h2>
                <p class="mt-1 text-xs text-ink-muted">Kumpulan indikator mentah per staff. Bobot poin per indikator belum diterapkan di sini.</p>
            </div>
            @if ($syncedAt)
                <span class="text-xs text-ink-muted">Sumber Collab tersinkron: {{ \Illuminate\Support\Carbon::parse($syncedAt)->format('d M Y') }}</span>
            @endif
        </div>

        @if (empty($rows))
            <p class="py-6 text-center text-sm text-ink-muted">Belum ada data pada periode/filter ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1280px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs text-ink-muted">
                            <th class="py-2 pr-3 font-medium">Wilayah</th>
                            <th class="py-2 pr-3 font-medium">Staff</th>
                            <th class="py-2 pr-3 font-medium">Kampus/Unit</th>
                            <th class="py-2 pr-3 text-right font-medium">Registrasi Personal</th>
                            <th class="py-2 pr-3 text-right font-medium">Herreg Personal</th>
                            <th class="py-2 pr-3 text-right font-medium">Registrasi Kampus</th>
                            <th class="py-2 pr-3 text-right font-medium">Herreg Kampus</th>
                            <th class="py-2 pr-3 text-right font-medium">Laporan Iklan</th>
                            <th class="py-2 pr-3 text-right font-medium">Realisasi Iklan</th>
                            <th class="py-2 pr-3 text-right font-medium">Follow Up</th>
                            <th class="py-2 pr-3 text-right font-medium">Leads</th>
                            <th class="py-2 pr-3 text-right font-medium">Total Laporan</th>
                            <th class="py-2 text-right font-medium">Hari Aktif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-border/60">
                                <td class="py-2 pr-3 text-ink-muted whitespace-nowrap">{{ $row['wilayah'] }}</td>
                                <td class="py-2 pr-3 font-medium text-ink whitespace-nowrap">{{ $row['name'] }}</td>
                                <td class="py-2 pr-3 text-ink-muted">{{ $row['unit_name'] }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['registrasi_personal'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['herregistrasi_personal'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['registrasi_kampus'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['herregistrasi_kampus'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['laporan_iklan'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['realisasi_iklan'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['follow_up_total'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['leads_total'], 0, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['laporan_total'], 0, ',', '.') }}</td>
                                <td class="py-2 text-right text-ink">{{ number_format($row['hari_aktif'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
