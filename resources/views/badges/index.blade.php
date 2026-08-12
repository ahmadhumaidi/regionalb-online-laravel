<x-layouts.app title="Badge & Achievement" active="badges">
    <section class="rounded-2xl glass-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Badge & Achievement</h2>
                <p class="mt-1 text-sm text-ink-muted">Ketentuan badge yang muncul di Arena Performa Staff dan Profil.</p>
            </div>
            <span class="rounded-lg glass-card-muted px-3 py-1.5 text-xs font-semibold text-ink-muted">{{ count($badges) }} badge aktif</span>
        </div>

        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($badges as $badge)
                <article class="rounded-xl border border-l-4 border-border p-4
                    @class([
                        'border-l-tone-blue' => $badge['tone'] === 'blue',
                        'border-l-tone-green' => $badge['tone'] === 'green',
                        'border-l-tone-purple' => $badge['tone'] === 'purple',
                        'border-l-tone-orange' => $badge['tone'] === 'orange',
                        'border-l-tone-red' => $badge['tone'] === 'red',
                    ])">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-sm font-black text-brand-700">{{ $loop->iteration }}</span>
                        <div>
                            <h3 class="text-sm font-bold text-ink">{{ $badge['name'] }}</h3>
                            <p class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Achievement</p>
                        </div>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Ketentuan</dt>
                            <dd class="mt-1 text-ink">{{ $badge['condition'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Sumber Data</dt>
                            <dd class="mt-1 text-ink-muted">{{ $badge['source'] }}</dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-5 rounded-2xl glass-card p-5">
        <h2 class="text-base font-semibold text-ink">{{ $fallback['name'] }}</h2>
        <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Ketentuan</dt>
                <dd class="mt-1 text-ink">{{ $fallback['condition'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Sumber Data</dt>
                <dd class="mt-1 text-ink-muted">{{ $fallback['source'] }}</dd>
            </div>
        </dl>
    </section>
</x-layouts.app>
