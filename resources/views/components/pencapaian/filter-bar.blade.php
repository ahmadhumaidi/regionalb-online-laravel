@props(['filters', 'referenceOptions'])
@php
    $user = auth()->user();
    $liveActive = request('periode') === 'daily';
    $currentMonthValue = substr($filters['date_from'], 0, 7);
    $periodeMonthOptions = [];
    $periodeCursor = \Illuminate\Support\Carbon::now('Asia/Jakarta')->startOfMonth();
    for ($i = 0; $i < 24; $i++) {
        $periodeMonthOptions[] = ['value' => $periodeCursor->format('Y-m'), 'label' => $periodeCursor->locale('id')->translatedFormat('F Y')];
        $periodeCursor = $periodeCursor->copy()->subMonthNoOverflow();
    }
@endphp

<form method="GET" action="{{ route('pencapaian') }}" class="mb-3 flex items-center gap-2">
    @foreach (request()->except(['periode', 'date_from', 'date_to']) as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endforeach
    <input type="hidden" name="periode" value="{{ $liveActive ? 'daily' : 'monthly' }}">
    <input type="hidden" name="date_from" value="{{ $currentMonthValue }}">
    <label class="grid gap-1">
        <span class="text-[11px] font-bold tracking-wide text-ink-muted uppercase">Periode</span>
        <select onchange="const o=this.options[this.selectedIndex];this.form.periode.value=o.dataset.periode;this.form.date_from.value=o.dataset.month;this.form.submit();" class="min-h-[36px] rounded-lg border border-border px-2.5 py-1.5 text-sm">
            <option data-periode="daily" data-month="" @selected($liveActive)>Live (Hari Ini)</option>
            @foreach ($periodeMonthOptions as $option)
                <option data-periode="monthly" data-month="{{ $option['value'] }}" @selected(! $liveActive && $currentMonthValue === $option['value'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    </label>
</form>

<form method="GET" action="{{ route('pencapaian') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl glass-card p-4">
    <input type="hidden" name="periode" value="{{ $liveActive ? 'daily' : 'monthly' }}">
    <input type="hidden" name="date_from" value="{{ $currentMonthValue }}">

    @if ($user->role === 'staff')
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Wilayah</label>
            <input value="{{ $user->regional }}" readonly title="Otomatis sesuai akun login" class="rounded-lg glass-card-muted px-3 py-2 text-sm text-ink-muted">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Staff</label>
            <input value="{{ $user->name }}" readonly title="Otomatis sesuai akun login" class="rounded-lg glass-card-muted px-3 py-2 text-sm text-ink-muted">
        </div>
    @else
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Wilayah</label>
            <select name="wilayah" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
                <option value="">Semua Wilayah</option>
                @foreach ($referenceOptions['regionals'] as $regional)
                    <option value="{{ $regional }}" @selected($filters['wilayah'] === $regional)>{{ $regional }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Staff</label>
            <select name="staff_name" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
                <option value="">Semua Staff</option>
                @foreach ($referenceOptions['staff'] as $staff)
                    <option value="{{ $staff['name'] }}" @selected($filters['staff_name'] === $staff['name'])>{{ $staff['name'] }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="ml-auto flex gap-2">
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Terapkan</button>
        <a href="{{ route('pencapaian') }}" class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-ink-muted hover:bg-surface-muted">Reset</a>
    </div>
</form>
