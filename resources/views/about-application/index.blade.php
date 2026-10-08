<x-layouts.app title="Tentang Aplikasi" active="about-application" eyebrow="Informasi Sistem">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="overflow-hidden rounded-2xl border border-border bg-surface shadow-sm">
            <div class="bg-slate-900 px-6 py-8 text-white sm:px-8">
                <p class="text-xs font-semibold tracking-[0.18em] text-orange-300 uppercase">Dashboard Regional B</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Sistem kerja terintegrasi untuk operasional regional</h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">Menyatukan pelaporan, pencapaian, target, anggaran, CRM, jadwal, kolaborasi, dan administrasi pengguna dalam satu lingkungan kerja yang dapat dipantau sesuai peran.</p>
            </div>

            <div class="grid gap-5 p-6 sm:grid-cols-3 sm:p-8">
                <div class="rounded-xl bg-surface-muted p-5"><p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Pengembang</p><p class="mt-2 font-semibold text-ink">Ahmad Humaidi</p></div>
                <div class="rounded-xl bg-surface-muted p-5"><p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Pengguna</p><p class="mt-2 font-semibold text-ink">Staf, Korwil &amp; Pimpinan</p></div>
                <div class="rounded-xl bg-surface-muted p-5"><p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Fokus</p><p class="mt-2 font-semibold text-ink">Efisiensi &amp; transparansi</p></div>
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">Dokumen resmi</p>
                    <h3 class="mt-1 text-lg font-bold text-ink">Laporan Kontribusi Dashboard Regional B</h3>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-ink-muted">Berisi tujuan, manfaat, cakupan fungsi, keunggulan, nilai kontribusi, rekomendasi evaluasi, dan ringkasan bukti pengembangan sistem.</p>
                    <p class="mt-2 text-xs text-ink-muted">PDF · 8 halaman · A4</p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-3">
                    <a href="{{ route('about-application.report') }}" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-600 px-4 py-2.5 text-sm font-semibold text-brand-600 transition hover:bg-brand-50"><x-icon name="eye" class="h-4 w-4" /> Lihat PDF</a>
                    <a href="{{ route('about-application.report') }}" download class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700"><x-icon name="document" class="h-4 w-4" /> Unduh PDF</a>
                </div>
            </div>
        </section>

        <p class="text-center text-xs text-ink-muted">Halaman dan dokumen ini hanya dapat diakses oleh akun Super User.</p>
    </div>
</x-layouts.app>
