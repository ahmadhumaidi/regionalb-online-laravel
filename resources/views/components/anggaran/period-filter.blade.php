@props(['period', 'periodOptions'])

<form method="GET" action="{{ route('anggaran') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl glass-card p-4">
    <label class="grid gap-1">
        <span class="text-[11px] font-bold tracking-wide text-ink-muted uppercase">Periode Iklan</span>
        <select name="ad_period" onchange="this.form.submit()" class="min-h-[36px] rounded-lg border border-border px-2.5 py-1.5 text-sm">
            @foreach ($periodOptions as $option)
                <option value="{{ $option['value'] }}" @selected($period === $option['value'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </label>
</form>
