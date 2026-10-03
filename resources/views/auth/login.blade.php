<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020617">
    <title>Masuk | Dashboard Regional</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="relative min-h-screen lg:grid lg:grid-cols-[1.08fr_0.92fr]">
        <section class="relative hidden min-h-screen overflow-hidden bg-slate-950 px-12 py-10 text-white lg:flex lg:flex-col lg:justify-between xl:px-20 xl:py-14">
            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                <div class="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-blue-600/25 blur-3xl"></div>
                <div class="absolute -bottom-24 right-0 h-96 w-96 rounded-full bg-cyan-400/15 blur-3xl"></div>
                <div class="absolute inset-0 opacity-[0.12]" style="background-image: linear-gradient(rgba(255,255,255,.15) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.15) 1px, transparent 1px); background-size: 48px 48px; mask-image: linear-gradient(to bottom, black, transparent 82%);"></div>
            </div>

            <div class="relative flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-white text-sm font-extrabold tracking-tight text-blue-700 shadow-lg shadow-blue-950/30">RB</span>
                <div><p class="font-bold tracking-tight">Regional Dashboard</p><p class="text-xs text-blue-200/70">Performance & Collaboration Hub</p></div>
            </div>

            <div class="relative max-w-xl py-16">
                <div class="mb-7 inline-flex items-center gap-2 rounded-full border border-blue-300/20 bg-blue-400/10 px-3 py-1.5 text-xs font-semibold text-blue-100 backdrop-blur">
                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-300 shadow-[0_0_10px_rgba(103,232,249,.9)]"></span>
                    Pusat informasi tim regional
                </div>
                <h1 class="text-4xl font-extrabold leading-tight tracking-[-0.04em] xl:text-5xl">Satu dashboard untuk<br><span class="bg-gradient-to-r from-blue-300 via-cyan-200 to-blue-400 bg-clip-text text-transparent">bergerak lebih cepat.</span></h1>
                <p class="mt-6 max-w-lg text-base leading-7 text-slate-300">Pantau performa, kelola aktivitas, dan satukan kolaborasi tim dalam ruang kerja yang terintegrasi.</p>

                <div class="mt-10 grid max-w-lg grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm"><svg class="mb-3 h-5 w-5 text-cyan-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19V9m6 10V5m6 14v-7m4 7H2" stroke-linecap="round"/></svg><p class="text-sm font-semibold">Performa</p><p class="mt-1 text-[11px] leading-4 text-slate-400">Data dalam satu layar</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm"><svg class="mb-3 h-5 w-5 text-blue-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87" stroke-linecap="round"/></svg><p class="text-sm font-semibold">Kolaborasi</p><p class="mt-1 text-[11px] leading-4 text-slate-400">Seluruh tim terhubung</p></div>
                    <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4 backdrop-blur-sm"><svg class="mb-3 h-5 w-5 text-violet-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z" stroke-linecap="round"/></svg><p class="text-sm font-semibold">Efisien</p><p class="mt-1 text-[11px] leading-4 text-slate-400">Keputusan lebih cepat</p></div>
                </div>
            </div>
            <p class="relative text-xs text-slate-500">&copy; {{ date('Y') }} {{ config('app.name') }}. Internal workspace.</p>
        </section>

        <section class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-10 sm:px-10 lg:px-14 xl:px-24">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-blue-50 to-transparent lg:hidden" aria-hidden="true"></div>
            <div class="relative w-full max-w-md">
                <div class="mb-10 flex items-center gap-3 lg:hidden"><span class="grid h-11 w-11 place-items-center rounded-xl bg-blue-700 text-sm font-extrabold text-white shadow-lg shadow-blue-700/20">RB</span><div><p class="font-bold tracking-tight">Regional Dashboard</p><p class="text-xs text-slate-500">Performance & Collaboration Hub</p></div></div>
                <div class="mb-8"><p class="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-blue-600">Selamat datang kembali</p><h2 class="text-3xl font-extrabold tracking-[-0.035em] text-slate-950 sm:text-4xl">Masuk ke akun Anda</h2><p class="mt-3 text-sm leading-6 text-slate-500">Gunakan akun regional Anda untuk melanjutkan ke dashboard.</p></div>

                @if ($errors->any())
                    <div role="alert" class="mb-6 flex gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3.5 text-sm text-red-700"><svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01" stroke-linecap="round"/></svg><div><p class="font-semibold">Tidak dapat masuk</p><p class="mt-0.5 text-xs text-red-600">{{ $errors->first() }}</p></div></div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5" x-data="{ showPassword: false, loading: false }" x-on:submit="loading = true">
                    @csrf
                    <div>
                        <label for="username" class="mb-2 block text-sm font-semibold text-slate-700">Username</label>
                        <div class="relative"><svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0m8-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke-linecap="round"/></svg><input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="h-13 w-full rounded-xl border border-slate-200 bg-white py-3 pl-12 pr-4 text-sm shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" placeholder="Masukkan username Anda"></div>
                    </div>
                    <div>
                        <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label>
                        <div class="relative"><svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="password" x-bind:type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" class="h-13 w-full rounded-xl border border-slate-200 bg-white py-3 pl-12 pr-12 text-sm shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-300 focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10" placeholder="Masukkan password Anda"><button type="button" x-on:click="showPassword = !showPassword" class="absolute right-3 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/30" x-bind:aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"><svg x-show="!showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg><svg x-show="showPassword" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 3 18 18M6.6 6.7C3.6 8.6 2 12 2 12s3.5 8 10 8a9.8 9.8 0 0 0 4-.8M9.9 4.2A10.8 10.8 0 0 1 12 4c6.5 0 10 8 10 8a16.5 16.5 0 0 1-2.1 3.2" stroke-linecap="round"/></svg></button></div>
                    </div>
                    <button type="submit" x-bind:disabled="loading" class="group flex h-13 w-full items-center justify-center gap-2 rounded-xl bg-blue-700 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-blue-700/20 transition hover:-translate-y-0.5 hover:bg-blue-800 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 disabled:cursor-wait disabled:opacity-75"><span x-text="loading ? 'Memproses...' : 'Masuk ke Dashboard'">Masuk ke Dashboard</span><svg x-show="!loading" class="h-4 w-4 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-5-5 5 5-5 5" stroke-linecap="round"/></svg><svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-30" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg></button>
                </form>
                <div class="mt-8 flex items-center justify-center gap-2 text-xs text-slate-400"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 22s8-3.5 8-10V5l-8-3-8 3v7c0 6.5 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>Akses aman untuk tim internal</div>
            </div>
        </section>
    </main>
</body>
</html>
