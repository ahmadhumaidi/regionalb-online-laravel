<x-layouts.app title="Monitoring Konten Kampus" active="konten">
    @if(session('status'))<div class="mb-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif @if($errors->any())<div class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <section class="mb-5"><form method="POST" action="{{ route('konten.accounts.store') }}" class="max-w-3xl rounded-2xl glass-card p-4">@csrf<h2 class="text-sm font-semibold text-ink">Tambah Akun Instagram</h2><div class="mt-3 grid gap-2 sm:grid-cols-2"><input name="wilayah" placeholder="Regional" required class="rounded-lg border-border bg-surface-muted"><input name="unit_name" placeholder="Unit/Kampus" required class="rounded-lg border-border bg-surface-muted"><input name="instagram_username" placeholder="@username" required class="rounded-lg border-border bg-surface-muted"><input name="pic_name" placeholder="PIC" class="rounded-lg border-border bg-surface-muted"><button class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white sm:col-span-2">Simpan akun</button></div></form></section>
    <x-konten.filter-bar :filters="$filters" :reference-options="$referenceOptions" />
    <x-dashboard.summary-cards :cards="$summaryCards" />
    @if($accounts !== [])
        <section class="mb-5 rounded-2xl glass-card p-4">
            <h2 class="text-sm font-semibold text-ink">Akun Instagram Aktif</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($accounts as $account)
                    <div class="rounded-xl border border-border bg-surface-muted px-3 py-2">
                        <p class="text-sm font-semibold text-ink">{{ $account['unit_name'] }}</p>
                        <p class="text-xs text-ink-muted">{{ '@'.ltrim($account['instagram_username'], '@') }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
    <x-konten.regional-table :regionals="$regionals" />
    <x-konten.posts-table :posts="$posts" />
</x-layouts.app>
