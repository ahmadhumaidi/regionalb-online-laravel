@props(['rows'])

<section class="rounded-2xl border border-border bg-surface p-5">
    <h2 class="mb-4 text-base font-semibold text-ink">Pencapaian Staff</h2>
    @if (empty($rows))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data staff pada periode/filter ini.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs text-ink-muted">
                        <th class="py-2 pr-3 font-medium">Regional</th>
                        <th class="py-2 pr-3 font-medium">NIK</th>
                        <th class="py-2 pr-3 font-medium">Staff</th>
                        <th class="py-2 pr-3 text-right font-medium">Registrasi</th>
                        <th class="py-2 text-right font-medium">Herregistrasi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-border/60 last:border-0">
                            <td class="py-2 pr-3 text-ink-muted">{{ $row['regional'] }}</td>
                            <td class="py-2 pr-3 text-ink-muted">{{ $row['nik'] ?: '-' }}</td>
                            <td class="py-2 pr-3 font-medium text-ink">{{ $row['name'] }}</td>
                            <td class="py-2 pr-3 text-right font-semibold text-ink">{{ number_format($row['registrasi'], 0, ',', '.') }}</td>
                            <td class="py-2 text-right text-ink">{{ number_format($row['herregistrasi'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
