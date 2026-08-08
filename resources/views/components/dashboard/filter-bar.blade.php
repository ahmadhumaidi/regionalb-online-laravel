@props(['filters', 'referenceOptions'])
@php $user = auth()->user(); @endphp

<form method="GET" action="{{ route('dashboard') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl glass-card p-4">
    <div>
        <label class="mb-1 block text-xs font-medium text-ink-muted">Dari tanggal</label>
        <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
    </div>
    <div>
        <label class="mb-1 block text-xs font-medium text-ink-muted">Sampai tanggal</label>
        <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
    </div>

    @if ($user->role === 'staff')
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Wilayah</label>
            <input value="{{ $user->regional }}" readonly title="Otomatis sesuai akun login" class="rounded-lg glass-card-muted px-3 py-2 text-sm text-ink-muted">
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Unit/Kampus</label>
            <input value="{{ $user->campus_name }}" readonly title="Otomatis sesuai akun login" class="rounded-lg glass-card-muted px-3 py-2 text-sm text-ink-muted">
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
            <label class="mb-1 block text-xs font-medium text-ink-muted">Unit/Kampus</label>
            <select name="unit_name" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
                <option value="">Semua Unit/Kampus</option>
                @foreach ($referenceOptions['campuses'] as $campus)
                    <option value="{{ $campus['label'] }}" @selected($filters['unit_name'] === $campus['label'])>{{ $campus['label'] }}</option>
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
        <a href="{{ route('dashboard') }}" class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-ink-muted hover:bg-surface-muted">Reset</a>
    </div>
</form>
