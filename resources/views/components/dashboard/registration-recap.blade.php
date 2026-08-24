@props(['recaps'])

<section
    class="relative mb-6 rounded-2xl glass-card p-5"
    x-data="{
        active: 0,
        total: {{ count($recaps) }},
        move(step) {
            this.goTo((this.active + step + this.total) % this.total)
        },
        goTo(index) {
            this.active = index
            this.$refs.track.scrollTo({ left: index * this.$refs.track.clientWidth, behavior: 'smooth' })
        },
    }"
>
    @if (count($recaps) > 1)
        <button
            type="button"
            @click="move(-1)"
            class="absolute top-1/2 left-2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-white/60 bg-white/35 text-brand-700 shadow-lg backdrop-blur-md transition hover:-translate-x-0.5 hover:bg-white/55 hover:text-brand-800 focus:ring-2 focus:ring-brand-500 focus:outline-none"
            aria-label="Regional sebelumnya"
            title="Regional sebelumnya"
        >
            <x-icon name="chevron-left" class="h-5 w-5" />
        </button>
        <button
            type="button"
            @click="move(1)"
            class="absolute top-1/2 right-2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-white/60 bg-white/35 text-brand-700 shadow-lg backdrop-blur-md transition hover:translate-x-0.5 hover:bg-white/55 hover:text-brand-800 focus:ring-2 focus:ring-brand-500 focus:outline-none"
            aria-label="Regional berikutnya"
            title="Regional berikutnya"
        >
            <x-icon name="chevron-right" class="h-5 w-5" />
        </button>
    @endif

    <div
        x-ref="track"
        class="flex snap-x snap-mandatory overflow-x-auto scroll-smooth [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        @scroll.debounce.75ms="active = Math.round($refs.track.scrollLeft / $refs.track.clientWidth)"
    >
        @foreach ($recaps as $recap)
            @php
                $collabItems = [
                    ['label' => 'Closing Kampus', 'value' => number_format($recap['total_closing_kampus'], 0, ',', '.'), 'tone' => 'blue-dark'],
                    ['label' => 'Herreg Kampus', 'value' => number_format($recap['total_herreg_kampus'], 0, ',', '.'), 'tone' => 'blue'],
                    ['label' => 'Closing Personal', 'value' => number_format($recap['total_closing_personal'], 0, ',', '.'), 'tone' => 'blue-light'],
                    ['label' => 'Herreg Personal', 'value' => number_format($recap['total_herreg_personal'], 0, ',', '.'), 'tone' => 'red'],
                    ['label' => 'Realisasi Iklan', 'value' => 'Rp '.number_format($recap['total_realisasi_iklan'], 0, ',', '.'), 'tone' => 'orange'],
                    ['label' => 'Reg / Herreg All', 'value' => number_format($recap['pmb_daftar'], 0, ',', '.').' / '.number_format($recap['pmb_herreg'], 0, ',', '.'), 'tone' => 'yellow'],
                ];
                $bdcItems = [
                    ['label' => 'Data BDC', 'value' => number_format($recap['bdc_total'], 0, ',', '.')],
                    ['label' => 'New Data', 'value' => number_format($recap['bdc_data_baru'], 0, ',', '.'), 'warn' => $recap['bdc_data_baru'] > 0],
                    ['label' => 'FU Hari Ini', 'value' => number_format($recap['bdc_fu_hari_ini'], 0, ',', '.')],
                    ['label' => 'Closing BDC', 'value' => number_format($recap['bdc_closing'], 0, ',', '.')],
                    ['label' => 'Wawancara', 'value' => number_format($recap['bdc_wawancara'], 0, ',', '.')],
                    ['label' => 'Belum Herreg', 'value' => number_format($recap['bdc_belum_herreg'], 0, ',', '.')],
                ];
            @endphp
            <div class="w-full shrink-0 snap-center">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-ink">{{ $recap['label'] }}</h2>
                    <div class="flex items-center gap-2">
                        <span class="flex h-[50px] w-[50px] shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-sm font-semibold text-white">
                            @if ($recap['person_photo'])
                                <img src="{{ $recap['person_photo'] }}" alt="{{ $recap['person_name'] }}" class="h-full w-full object-cover">
                            @else
                                {{ strtoupper(mb_substr($recap['person_name'] ?: 'K', 0, 1)) }}
                            @endif
                        </span>
                        <span class="leading-tight">
                            <span class="block text-xs font-semibold text-ink">{{ $recap['person_name'] }}</span>
                            <span class="block text-[11px] text-ink-muted">{{ $recap['person_jabatan'] }}</span>
                        </span>
                    </div>
                </div>

                <p class="mb-2 text-xs font-bold tracking-wide text-ink-muted uppercase">Sumber Collab</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($collabItems as $item)
                        <div class="rounded-xl border border-ink/15 border-t-4 bg-surface-muted/70 p-3 text-center shadow-sm" style="border-top-color: var(--color-tone-{{ $item['tone'] }})">
                            <span class="block text-xs font-medium text-ink">{{ $item['label'] }}</span>
                            <strong class="mt-1 block text-xl font-bold" style="color: var(--color-tone-{{ $item['tone'] }})">{{ $item['value'] }}</strong>
                        </div>
                    @endforeach
                </div>

                <div class="my-4 border-t border-border/60"></div>

                <p class="mb-2 text-xs font-bold tracking-wide text-ink-muted uppercase">Sumber BDC</p>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($bdcItems as $item)
                        <div class="relative rounded-xl border border-ink/15 border-t-4 bg-surface-muted/70 p-3 text-center shadow-sm" style="border-top-color: var(--color-tone-cyan)">
                            @if (! empty($item['warn']))
                                <span class="absolute top-1.5 right-1.5 animate-denyut text-tone-red" title="Segera follow up">
                                    <x-icon name="warning" class="h-4 w-4" />
                                </span>
                            @endif
                            <span class="block text-xs font-medium text-ink">{{ $item['label'] }}</span>
                            <strong class="mt-1 block text-xl font-bold" style="color: var(--color-tone-cyan)">{{ $item['value'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @if (count($recaps) > 1)
        <div class="mt-4 flex items-center justify-center gap-2">
            @foreach ($recaps as $i => $recap)
                <button
                    type="button"
                    @click="goTo({{ $i }})"
                    class="h-2 rounded-full transition-all"
                    :class="active === {{ $i }} ? 'w-6 bg-brand-600' : 'w-2 bg-border'"
                    aria-label="{{ $recap['label'] }}"
                ></button>
            @endforeach
        </div>
    @endif
</section>
