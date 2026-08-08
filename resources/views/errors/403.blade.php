<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses ditolak - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-surface-muted px-4 font-sans text-ink antialiased">
    <div class="w-full max-w-sm rounded-2xl glass-card p-8 text-center shadow-sm">
        <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-tone-red/10 text-tone-red">
            <x-icon name="ban" class="h-7 w-7" />
        </span>
        <h1 class="text-lg font-semibold text-ink">Akses ditolak</h1>
        <p class="mt-2 text-sm text-ink-muted">{{ $exception->getMessage() ?: 'Role Anda tidak memiliki akses ke halaman ini.' }}</p>
        <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="mt-6 inline-flex w-full items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">
            Kembali ke Dashboard
        </a>
    </div>
</body>
</html>
