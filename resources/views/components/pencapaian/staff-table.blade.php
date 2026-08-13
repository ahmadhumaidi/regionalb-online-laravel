@props(['rowsByRegional'])

@if (empty($rowsByRegional))
    <section class="rounded-2xl glass-card p-5">
        <h2 class="mb-4 text-base font-semibold text-ink">Pencapaian Staff</h2>
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada data staff pada periode/filter ini.</p>
    </section>
@else
    <div class="space-y-4">
        @foreach ($rowsByRegional as $group)
            <section class="rounded-2xl glass-card p-5">
                <div class="mb-4 flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold text-ink">Pencapaian Staff &middot; {{ $group['regional'] }}</h2>
                    <span class="rounded-lg glass-card-muted px-3 py-1.5 text-xs font-semibold text-ink-muted">{{ count($group['rows']) }} staff</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[11px] sm:text-sm">
                        <thead>
                            <tr class="border-b border-border text-[10px] text-ink-muted sm:text-xs">
                                <th class="py-1.5 pr-2 font-medium whitespace-nowrap sm:py-2 sm:pr-3">#</th>
                                <th class="py-1.5 pr-2 font-medium sm:py-2 sm:pr-3">Staff</th>
                                <th class="py-1.5 pr-2 font-medium sm:py-2 sm:pr-3">Kampus/Unit</th>
                                <th class="py-1.5 pr-2 text-right font-medium whitespace-nowrap sm:py-2 sm:pr-3">Registrasi</th>
                                <th class="py-1.5 text-right font-medium whitespace-nowrap sm:py-2">Herreg</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group['rows'] as $i => $row)
                                <tr class="border-b border-border/60 last:border-0">
                                    <td class="py-1.5 pr-2 whitespace-nowrap text-ink-muted sm:py-2 sm:pr-3">{{ $i + 1 }}</td>
                                    <td class="py-1.5 pr-2 font-medium text-ink sm:py-2 sm:pr-3">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-[10px] font-semibold text-white">
                                                @if ($row['photo'] ?? null)
                                                    <img src="{{ $row['photo'] }}" alt="{{ $row['name'] }}" class="h-full w-full object-cover">
                                                @else
                                                    {{ strtoupper(mb_substr($row['name'] ?: 'S', 0, 1)) }}
                                                @endif
                                            </span>
                                            <span>{{ $row['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="py-1.5 pr-2 text-ink-muted sm:py-2 sm:pr-3">{{ $row['campus_name'] ?? '-' }}</td>
                                    <td class="py-1.5 pr-2 text-right font-semibold whitespace-nowrap text-ink sm:py-2 sm:pr-3">{{ number_format($row['registrasi'], 0, ',', '.') }}</td>
                                    <td class="py-1.5 text-right whitespace-nowrap text-ink sm:py-2">{{ number_format($row['herregistrasi'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
@endif
