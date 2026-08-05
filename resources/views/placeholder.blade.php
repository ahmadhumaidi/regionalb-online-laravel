<x-layouts.app :title="$pageTitle" :active="$active">
    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-surface px-6 py-20 text-center">
        <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600">
            <x-icon name="empty" class="h-7 w-7" />
        </span>
        <h2 class="text-base font-semibold text-ink">{{ $pageTitle }}</h2>
        <p class="mt-2 max-w-sm text-sm text-ink-muted">
            Halaman ini masih memakai data dari aplikasi lama dan akan dipindahkan ke Laravel pada tahap migrasi berikutnya.
        </p>
    </div>
</x-layouts.app>
