<x-layouts.app title="Badge & Achievement" active="badges">
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="rounded-2xl glass-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Badge & Achievement</h2>
                <p class="mt-1 text-sm text-ink-muted">Ketentuan badge yang muncul di Arena Performa Staff dan Profil.</p>
            </div>
            <span class="rounded-lg glass-card-muted px-3 py-1.5 text-xs font-semibold text-ink-muted">{{ count($badges) }} badge aktif</span>
        </div>

        <form method="POST" action="{{ route('badges.update') }}" class="mt-5">
            @csrf
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
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
                                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Indikator</dt>
                                <dd class="mt-1">
                                    <select
                                        name="settings[{{ $badge['key'] }}][indicator_key]"
                                        @disabled(! $canManageBadges)
                                        class="w-full rounded-lg border-border bg-surface-muted text-sm"
                                    >
                                        @foreach ($indicators as $indicatorKey => $indicator)
                                            <option value="{{ $indicatorKey }}" @selected(old('settings.'.$badge['key'].'.indicator_key', $badge['indicator_key']) === $indicatorKey)>
                                                {{ $indicator['label'] }} · {{ $indicator['group'] ?? 'Indikator' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Nilai Dicapai</dt>
                                <dd class="mt-1">
                                    <input
                                        type="number"
                                        min="0"
                                        step="{{ $indicators[$badge['indicator_key']]['step'] ?? '1' }}"
                                        name="settings[{{ $badge['key'] }}][target_value]"
                                        value="{{ old('settings.'.$badge['key'].'.target_value', (float) $badge['target_value']) }}"
                                        @disabled(! $canManageBadges)
                                        class="w-full rounded-lg border-border bg-surface-muted"
                                    >
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Indikator Terpilih</dt>
                                <dd class="mt-1 text-ink">{{ $badge['indicator_label'] }}</dd>
                            </div>
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

            @if ($canManageBadges)
                <div class="mt-4 flex justify-end">
                    <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Simpan ketentuan badge</button>
                </div>
            @endif
        </form>
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
