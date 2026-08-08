@props(['regionals'])

<section class="mb-6 rounded-2xl glass-card p-5">
    <h2 class="mb-4 text-base font-semibold text-ink">Ringkasan Regional</h2>
    @if (empty($regionals))
        <p class="py-6 text-center text-sm text-ink-muted">Belum ada aktivitas konten pada periode/filter ini.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs text-ink-muted">
                        <th class="py-2 pr-3 font-medium">Regional</th>
                        <th class="py-2 pr-3 text-right font-medium">Feed</th>
                        <th class="py-2 pr-3 text-right font-medium">Reels</th>
                        <th class="py-2 pr-3 text-right font-medium">Story</th>
                        <th class="py-2 pr-3 text-right font-medium">Post</th>
                        <th class="py-2 text-right font-medium">Poin</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($regionals as $row)
                        <tr class="border-b border-border/60 last:border-0">
                            <td class="py-2 pr-3 font-medium text-ink">{{ $row['wilayah'] }}</td>
                            <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['feed'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['reels'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['story'], 0, ',', '.') }}</td>
                            <td class="py-2 pr-3 text-right text-ink">{{ number_format($row['posts'], 0, ',', '.') }}</td>
                            <td class="py-2 text-right font-semibold text-ink">{{ number_format($row['score'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
