@props(['ranking'])

<section class="mb-6 rounded-2xl border border-border bg-surface p-5">
    <h2 class="mb-4 text-base font-semibold text-ink">Ranking Top 10 Unit/Kampus</h2>
    @if (empty($ranking))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data pada periode/filter ini.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs text-ink-muted">
                        <th class="py-2 pr-3 font-medium">#</th>
                        <th class="py-2 pr-3 font-medium">Unit/Kampus</th>
                        <th class="py-2 pr-3 text-right font-medium">Leads</th>
                        <th class="py-2 pr-3 text-right font-medium">Registrasi</th>
                        <th class="py-2 pr-3 text-right font-medium">Herreg</th>
                        <th class="py-2 pr-3 text-right font-medium">Conv.</th>
                        <th class="py-2 text-right font-medium">CPL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ranking as $i => $row)
                        <tr class="border-b border-border/60 last:border-0">
                            <td class="py-2 pr-3 text-ink-muted">{{ $i + 1 }}</td>
                            <td class="py-2 pr-3 font-medium text-ink">{{ $row['unit_label'] }}</td>
                            <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['leads'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-ink">{{ number_format($row['registrasi'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['herregistrasi'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['conversion_rate'], 1, ',', '.') }}%</td>
                            <td class="py-2 text-right text-ink">Rp {{ number_format($row['cpl'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
