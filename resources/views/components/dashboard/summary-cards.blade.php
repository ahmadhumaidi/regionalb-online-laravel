@props(['cards'])

<section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ($cards as $card)
        <article class="flex min-h-28 flex-col rounded-2xl glass-card border-t-4 px-3 pt-2 pb-4" style="border-top-color: var(--color-tone-{{ $card['tone'] }})">
            <span class="block text-center text-[11px] font-bold text-ink">{{ $card['label'] }}</span>
            <div class="flex flex-1 items-center justify-center">
                <strong class="text-center text-3xl font-semibold" style="color: var(--color-tone-{{ $card['tone'] }})">{{ $card['value'] }}</strong>
            </div>
            @if (! empty($card['note']))
                <small class="block text-center text-xs text-ink-muted">{{ $card['note'] }}</small>
            @endif
        </article>
    @endforeach
</section>
