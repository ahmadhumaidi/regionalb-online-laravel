@props(['filters', 'referenceOptions'])
@php $user = auth()->user(); @endphp

<div class="mb-3 flex flex-wrap gap-2 text-xs font-semibold">
    <a href="{{ route('pencapaian', array_merge(request()->query(), ['periode' => 'monthly'])) }}" class="rounded-lg px-3 py-1.5 {{ request('periode') !== 'daily' ? 'bg-brand-600 text-white' : 'border border-border text-ink-muted hover:bg-surface-muted' }}">Bulan Ini</a>
    <a href="{{ route('pencapaian', array_merge(request()->query(), ['periode' => 'daily'])) }}" class="rounded-lg px-3 py-1.5 {{ request('periode') === 'daily' ? 'bg-brand-600 text-white' : 'border border-border text-ink-muted hover:bg-surface-muted' }}">Live (Hari Ini)</a>
</div>
<form method="GET" action="{{ route('pencapaian') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl glass-card p-4">
    <input type="hidden" name="periode" value="{{ request('periode', 'monthly') }}">
    @if (request('periode') === 'daily')
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Tanggal</label>
            <input type="text" value="{{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }} (Live)" disabled class="rounded-lg border border-border px-3 py-2 text-sm text-ink-muted">
        </div>
    @else
        <div>
            <label class="mb-1 block text-xs font-medium text-ink-muted">Bulan</label>
            <input type="month" name="date_from" value="{{ substr($filters['date_from'], 0, 7) }}" class="rounded-lg border border-border px-3 py-2 text-sm text-ink">
        </div>
    @endif

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
