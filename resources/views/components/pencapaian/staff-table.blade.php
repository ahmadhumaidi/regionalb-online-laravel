@props(['rows'])

<section class="rounded-2xl glass-card p-5">
    <h2 class="mb-4 text-base font-semibold text-ink">Pencapaian Staff</h2>
    @if (empty($rows))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data staff pada periode/filter ini.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[11px] sm:text-sm">
                <thead>
                    <tr class="border-b border-border text-[10px] text-ink-muted sm:text-xs">
                        <th class="py-1.5 pr-2 font-medium whitespace-nowrap sm:py-2 sm:pr-3">Regional</th>
                        <th class="py-1.5 pr-2 font-medium whitespace-nowrap sm:py-2 sm:pr-3">NIK</th>
                        <th class="py-1.5 pr-2 font-medium sm:py-2 sm:pr-3">Staff</th>
                        <th class="py-1.5 pr-2 text-right font-medium whitespace-nowrap sm:py-2 sm:pr-3">Registrasi</th>
                        <th class="py-1.5 text-right font-medium whitespace-nowrap sm:py-2">Herreg</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-border/60 last:border-0">
                            <td class="py-1.5 pr-2 whitespace-nowrap text-ink-muted sm:py-2 sm:pr-3">{{ $row['regional'] }}</td>
                            <td class="py-1.5 pr-2 whitespace-nowrap text-ink-muted sm:py-2 sm:pr-3">{{ $row['nik'] ?: '-' }}</td>
                            <td class="py-1.5 pr-2 font-medium text-ink sm:py-2 sm:pr-3">{{ $row['name'] }}</td>
                            <td class="py-1.5 pr-2 text-right font-semibold whitespace-nowrap text-ink sm:py-2 sm:pr-3">{{ number_format($row['registrasi'], 0, ',', '.') }}</td>
                            <td class="py-1.5 text-right whitespace-nowrap text-ink sm:py-2">{{ number_format($row['herregistrasi'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
