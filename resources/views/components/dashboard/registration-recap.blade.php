@props(['recap', 'budget'])

<section class="mb-6 rounded-2xl glass-card p-5">
    <div class="mb-4">
        <h2 class="text-base font-semibold text-ink">Rekap Pencapaian Registrasi</h2>
        <p class="text-sm text-ink-muted">Ringkasan hasil PMB dari filter aktif</p>
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <div>
            <span class="text-xs font-medium text-ink-muted">Total Registrasi</span>
            <strong class="mt-1 block text-3xl font-bold text-ink">{{ number_format($recap['registrasi'], 0, ',', '.') }}</strong>
            <small class="text-xs text-ink-muted">Acuan: {{ $recap['registrasi_source'] }}</small>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-surface-muted">
                <div class="h-full rounded-full bg-brand-600" style="width: {{ $recap['target_registrasi'] > 0 ? max(3, min(100, $recap['target_progress'])) : 0 }}%"></div>
            </div>
            @if ($recap['target_registrasi'] > 0)
                <p class="mt-1.5 text-xs text-ink-muted">
                    {{ number_format($recap['target_progress'], 2, ',', '.') }}% dari target {{ number_format($recap['target_registrasi'], 0, ',', '.') }} registrasi
                    @if ($recap['target_staff_count'] > 0)
                        ({{ number_format($recap['target_staff_count'], 0, ',', '.') }} staff)
                    @endif
                </p>
            @else
                <p class="mt-1.5 text-xs text-ink-muted">Target registrasi belum diatur untuk periode/filter ini.</p>
            @endif
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between"><span class="text-sm text-ink-muted">Leads Masuk</span><strong class="text-sm font-semibold text-ink">{{ number_format($recap['leads'], 0, ',', '.') }}</strong></div>
            <div>
                <div class="flex items-center justify-between"><span class="text-sm text-ink-muted">Herregistrasi</span><strong class="text-sm font-semibold text-ink">{{ number_format($recap['herregistrasi'], 0, ',', '.') }}</strong></div>
                <small class="block text-xs text-ink-muted">Acuan: {{ $recap['herregistrasi_source'] }}</small>
            </div>
            <div class="flex items-center justify-between"><span class="text-sm text-ink-muted">Cost / Registrasi</span><strong class="text-sm font-semibold text-ink">Rp {{ number_format($recap['cost_per_registrasi'], 0, ',', '.') }}</strong></div>
        </div>

        <div>
            <div class="mb-2">
                <strong class="block text-sm font-semibold text-ink">Monitoring Anggaran Iklan</strong>
                <span class="text-xs text-ink-muted">pengajuan, spend, CPL</span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-ink-muted">Pengajuan</span><strong class="text-ink">Rp {{ number_format($budget['requested'], 0, ',', '.') }}</strong></div>
                <div class="flex justify-between"><span class="text-ink-muted">Spend</span><strong class="text-ink">Rp {{ number_format($budget['spend'], 0, ',', '.') }}</strong></div>
                <div class="flex justify-between"><span class="text-ink-muted">Sisa</span><strong class="text-ink">Rp {{ number_format($budget['remaining'], 0, ',', '.') }}</strong></div>
                <div class="flex justify-between"><span class="text-ink-muted">CPL</span><strong class="text-ink">Rp {{ number_format($budget['cpl'], 0, ',', '.') }}</strong></div>
            </div>
        </div>
    </div>
</section>
