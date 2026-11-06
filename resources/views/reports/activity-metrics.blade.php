<label class="grid gap-1 text-sm">Durasi (menit)
    <input type="number" name="duration_minutes" min="1" max="10080" step="1" value="{{ old('duration_minutes', $report->duration_minutes) }}" placeholder="Contoh: 30" class="rounded-lg border-border bg-surface-muted">
</label>
<label class="grid gap-1 text-sm">Skala urgensi
    <select name="urgency" class="rounded-lg border-border bg-surface-muted">
        <option value="">Pilih urgensi</option>
        @foreach ([1 => 'Rendah', 2 => 'Sedang', 3 => 'Tinggi'] as $value => $label)
            <option value="{{ $value }}" @selected((string) old('urgency', $report->urgency) === (string) $value)>{{ $value }} - {{ $label }}</option>
        @endforeach
    </select>
</label>
