<x-layouts.app title="Ganti Password" active="password">
    <div class="mx-auto max-w-md rounded-2xl glass-card p-6 shadow-sm">
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-tone-red/30 bg-tone-red/10 px-3 py-2.5 text-sm font-medium text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
            @csrf
            <div>
                <label for="old_password" class="mb-1.5 block text-sm font-medium text-ink">Password Lama</label>
                <input id="old_password" type="password" name="old_password" required
                    class="w-full rounded-lg glass-card px-3 py-2.5 text-sm text-ink focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
            </div>
            <div>
                <label for="new_password" class="mb-1.5 block text-sm font-medium text-ink">Password Baru</label>
                <input id="new_password" type="password" name="new_password" required minlength="6"
                    class="w-full rounded-lg glass-card px-3 py-2.5 text-sm text-ink focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                <p class="mt-1 text-xs text-ink-muted">Minimal 6 karakter.</p>
            </div>
            <div>
                <label for="confirm_password" class="mb-1.5 block text-sm font-medium text-ink">Konfirmasi Password Baru</label>
                <input id="confirm_password" type="password" name="confirm_password" required minlength="6"
                    class="w-full rounded-lg glass-card px-3 py-2.5 text-sm text-ink focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
            </div>
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-700">
                Simpan Password
            </button>
        </form>
    </div>
</x-layouts.app>
