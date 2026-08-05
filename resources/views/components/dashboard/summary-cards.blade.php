@props(['cards'])

<section class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
    @foreach ($cards as $card)
        <article class="rounded-2xl border border-border bg-surface p-4">
            <span class="block text-xs font-medium text-ink-muted">{{ $card['label'] }}</span>
            <strong class="mt-1 block text-xl font-semibold" style="color: var(--color-tone-{{ $card['tone'] }})">{{ $card['value'] }}</strong>
            <small class="mt-1 block text-xs text-ink-muted">{{ $card['note'] }}</small>
        </article>
    @endforeach
</section>
