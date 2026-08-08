@props(['filters', 'referenceOptions', 'isSeniorTier' => false])
@php $user = auth()->user(); @endphp

<form method="GET" action="{{ route('dashboard') }}" class="mb-4 flex flex-wrap items-center justify-end gap-2">
    <input type="hidden" name="periode" value="monthly">
    <input type="month" name="date_from" value="{{ substr($filters['date_from'], 0, 7) }}" title="Bulan" class="rounded-lg border border-border bg-surface px-2 py-1 text-xs text-ink">

    @if ($user->role === 'staff')
        <input value="{{ $user->regional }}" readonly title="Wilayah - otomatis sesuai akun login" class="w-28 rounded-lg glass-card-muted px-2 py-1 text-xs text-ink-muted">
        <input value="{{ $user->campus_name }}" readonly title="Unit/Kampus - otomatis sesuai akun login" class="w-28 rounded-lg glass-card-muted px-2 py-1 text-xs text-ink-muted">
        <input value="{{ $user->name }}" readonly title="Staff - otomatis sesuai akun login" class="w-28 rounded-lg glass-card-muted px-2 py-1 text-xs text-ink-muted">
    @elseif (! $isSeniorTier)
        <select name="wilayah" title="Wilayah" class="rounded-lg border border-border bg-surface px-2 py-1 text-xs text-ink">
            <option value="">Semua Wilayah</option>
            @foreach ($referenceOptions['regionals'] as $regional)
                <option value="{{ $regional }}" @selected($filters['wilayah'] === $regional)>{{ $regional }}</option>
            @endforeach
        </select>
        <select name="unit_name" title="Unit/Kampus" class="rounded-lg border border-border bg-surface px-2 py-1 text-xs text-ink">
            <option value="">Semua Unit/Kampus</option>
            @foreach ($referenceOptions['campuses'] as $campus)
                <option value="{{ $campus['label'] }}" @selected($filters['unit_name'] === $campus['label'])>{{ $campus['label'] }}</option>
            @endforeach
        </select>
        <select name="staff_name" title="Staff" class="rounded-lg border border-border bg-surface px-2 py-1 text-xs text-ink">
            <option value="">Semua Staff</option>
            @foreach ($referenceOptions['staff'] as $staff)
                <option value="{{ $staff['name'] }}" @selected($filters['staff_name'] === $staff['name'])>{{ $staff['name'] }}</option>
            @endforeach
        </select>
    @endif

    <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1 text-xs font-semibold text-white hover:bg-brand-700">Terapkan</button>
    <a href="{{ route('dashboard') }}" class="rounded-lg border border-border px-3 py-1 text-xs font-medium text-ink-muted hover:bg-surface-muted">Reset</a>
</form>
