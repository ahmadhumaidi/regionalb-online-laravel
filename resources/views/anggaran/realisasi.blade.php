<x-layouts.app title="Lapor Realisasi Iklan" :active="'anggaran'">
    <section class="rounded-2xl border border-border bg-surface p-5">
        <div class="mb-5 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">Lapor Realisasi Iklan</h2>
                <p class="mt-1 text-sm text-ink-muted">{{ $report->campaign_name }} · {{ $report->platform }} · {{ $report->ad_period }}</p>
            </div>
            <a href="{{ route('anggaran') }}" class="rounded-lg border border-border px-3 py-2 text-sm">Kembali</a>
        </div>

        <dl class="mb-5 grid gap-4 text-sm md:grid-cols-3">
            <div><dt class="text-xs text-ink-muted">Wilayah</dt><dd class="mt-1 text-ink">{{ $report->wilayah ?: '-' }}</dd></div>
            <div><dt class="text-xs text-ink-muted">Unit/Kampus</dt><dd class="mt-1 text-ink">{{ $report->unit_name ?: '-' }}</dd></div>
            <div><dt class="text-xs text-ink-muted">Anggaran Disetujui</dt><dd class="mt-1 text-ink">Rp {{ number_format($report->budget_approved, 0, ',', '.') }}</dd></div>
        </dl>

        <form method="POST" enctype="multipart/form-data" action="{{ route('anggaran.realisasi.store', $report) }}" class="grid gap-4 md:grid-cols-2">
            @csrf
            @if ($errors->any())
                <div class="md:col-span-2 rounded-lg border border-tone-red/30 bg-tone-red/10 p-3 text-sm text-red-800">
                    <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <label class="grid gap-1 text-sm">Realisasi Anggaran (Rp)
                <input type="number" min="0.01" step="0.01" name="realization_amount" required value="{{ old('realization_amount', $report->realization_amount ?: '') }}" class="rounded-lg border-border bg-surface-muted">
            </label>
            <label class="grid gap-1 text-sm">Jumlah Leads
                <input type="number" min="0" step="1" name="leads_count" required value="{{ old('leads_count', $report->leads_count ?: 0) }}" class="rounded-lg border-border bg-surface-muted">
            </label>
            <label class="grid gap-1 text-sm">Jumlah Closing
                <input type="number" min="0" step="1" name="closing_count" required value="{{ old('closing_count', $report->closing_count ?: 0) }}" class="rounded-lg border-border bg-surface-muted">
            </label>

            <label class="grid gap-1 text-sm md:col-span-2">Upload Bukti
                <input type="file" name="attachment_path" accept="image/jpeg,image/png,image/webp,application/pdf" {{ $report->attachment_path ? '' : 'required' }} class="rounded-lg border-border bg-surface-muted">
                <span class="text-xs text-ink-muted">JPG, PNG, WEBP, atau PDF; maksimal 5 MB.</span>
            </label>
            @if ($report->attachment_path)
                <p class="text-xs text-ink-muted md:col-span-2"><a class="underline" href="{{ route('reports.attachment', $report) }}">Lihat lampiran saat ini</a></p>
            @endif

            <div class="md:col-span-2">
                <button class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Simpan Realisasi</button>
            </div>
        </form>
    </section>
</x-layouts.app>
