@props(['period', 'periodOptions'])

<form method="GET" action="{{ route('anggaran') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-surface p-4">
    <div>
        <label class="mb-1 block text-xs font-medium text-ink-muted">Periode Iklan</label>
        <select name="ad_period" onchange="this.form.submit()" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
            @foreach ($periodOptions as $option)
                <option value="{{ $option['value'] }}" @selected($period === $option['value'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </div>
</form>
