<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-surface-muted px-4 font-sans text-ink antialiased">
    <div class="w-full max-w-sm">
        <div class="mb-8 text-center">
            <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-600 text-lg font-bold text-white">RB</span>
            <h1 class="text-xl font-semibold text-ink">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-ink-muted">Masuk untuk melanjutkan ke dashboard regional.</p>
        </div>

        <div class="rounded-2xl glass-card p-6 shadow-sm">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-tone-red/30 bg-tone-red/10 px-3 py-2.5 text-sm font-medium text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="username" class="mb-1.5 block text-sm font-medium text-ink">Username</label>
                    <input
                        id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                        class="w-full rounded-lg glass-card px-3 py-2.5 text-sm text-ink placeholder:text-ink-muted focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none"
                        placeholder="Masukkan username">
                </div>
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-ink">Password</label>
                    <input
                        id="password" type="password" name="password" required
                        class="w-full rounded-lg glass-card px-3 py-2.5 text-sm text-ink placeholder:text-ink-muted focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none"
                        placeholder="Masukkan password">
                </div>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-700">
                    Masuk
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-ink-muted">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </div>
</body>
</html>
