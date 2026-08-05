<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface-muted font-sans text-ink antialiased" x-data="{ sidebarOpen: false }">

    <div class="flex min-h-screen">
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full transform flex-col border-r border-border bg-surface-sidebar transition-transform duration-200 lg:static lg:translate-x-0"
            :class="sidebarOpen && '!translate-x-0'"
        >
            <a href="{{ route('profile') }}" class="flex items-center gap-3 border-b border-border px-5 py-5 hover:bg-brand-50/60">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-600 text-sm font-semibold text-white">
                    @if ($user->photo_path)
                        <img src="{{ $user->photo_path }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                    @else
                        {{ strtoupper(mb_substr($user->name ?: 'U', 0, 1)) }}
                    @endif
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold text-ink">{{ $user->name }}</span>
                    <span class="block truncate text-xs text-ink-muted">{{ $user->campus_name ?: 'Kampus belum diatur' }}</span>
                    <span class="block truncate text-xs text-ink-muted">{{ $user->jabatan ?: \App\Support\RsmRole::label($user->role) }}</span>
                </span>
            </a>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
                @foreach ($menuSections as $section)
                    <div>
                        <p class="px-2 text-xs font-semibold tracking-wide text-ink-muted uppercase">{{ $section['title'] }}</p>
                        <div class="mt-2 space-y-0.5">
                            @foreach ($section['items'] as $item)
                                @php $isActive = $active === $item['key']; @endphp
                                <a
                                    href="{{ \App\Support\Menu::routeFor($item['key']) }}"
                                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $isActive ? 'bg-brand-600 text-white shadow-sm' : 'text-ink hover:bg-brand-50 hover:text-brand-700' }}"
                                >
                                    <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 {{ $isActive ? 'text-white' : 'text-ink-muted' }}" />
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-3 border-b border-border bg-surface px-4 py-3 lg:px-8">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" type="button" class="rounded-lg p-2 text-ink-muted hover:bg-brand-50 hover:text-brand-700 lg:hidden">
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>
                    <div>
                        <p class="text-xs font-semibold tracking-wide text-brand-600 uppercase">{{ $eyebrow }}</p>
                        <h1 class="text-lg font-semibold text-ink">{{ $title }}</h1>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if (count($allowedRoleKeys ?? []) > 1)
                        <form method="GET" action="{{ url()->current() }}">
                            @foreach (request()->except('role') as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <label class="flex items-center gap-1.5 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-ink-muted">
                                <span class="hidden sm:inline">Tampilan sebagai</span>
                                <select name="role" onchange="this.form.submit()" class="bg-transparent text-sm font-medium text-ink focus:outline-none">
                                    @foreach ($allowedRoleKeys as $roleKey)
                                        <option value="{{ $roleKey }}" @selected(($effectiveRole ?? null) === $roleKey)>{{ \App\Support\RsmRole::label($roleKey) }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </form>
                    @endif

                    @if ($impersonationUsers->isNotEmpty())
                        <form method="POST" action="{{ route('impersonation.store') }}">
                            @csrf
                            <label class="flex items-center gap-1.5 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-ink-muted">
                                <x-icon name="switch" class="h-4 w-4" />
                                <select name="user_id" onchange="this.form.submit()" class="bg-transparent text-sm font-medium text-ink focus:outline-none">
                                    @foreach ($impersonationUsers as $candidate)
                                        <option value="{{ $candidate->id }}" @selected($candidate->id === $user->id)>{{ $candidate->name }} &mdash; {{ $candidate->jabatan ?: \App\Support\RsmRole::label($candidate->role) }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </form>
                    @endif

                    <div class="hidden rounded-lg border border-border bg-surface px-3 py-1.5 text-right sm:block">
                        <p class="text-sm font-semibold text-ink">{{ $user->name }}</p>
                        <p class="text-xs text-ink-muted">{{ $user->username }} &middot; {{ $user->jabatan ?: \App\Support\RsmRole::label($user->role) }}</p>
                    </div>

                    <button type="button" class="rounded-lg border border-border bg-surface p-2 text-ink-muted hover:bg-brand-50 hover:text-brand-700" title="Notifikasi">
                        <x-icon name="bell" class="h-5 w-5" />
                    </button>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-2 text-sm font-medium text-ink-muted hover:border-tone-red hover:text-tone-red">
                            <x-icon name="logout" class="h-4 w-4" />
                            <span class="hidden sm:inline">Keluar</span>
                        </button>
                    </form>
                </div>
            </header>

            @if (session('impersonation.original_id'))
                <div class="flex flex-wrap items-center justify-between gap-3 bg-tone-amber/10 px-4 py-2.5 text-sm text-amber-800 lg:px-8">
                    <span>Sedang masuk sebagai <strong>{{ $user->name }}</strong>.</span>
                    <form method="POST" action="{{ route('impersonation.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-semibold underline underline-offset-2">Kembali ke akun asli</button>
                    </form>
                </div>
            @endif

            @if (session('notice'))
                <div class="mx-4 mt-4 rounded-lg border border-tone-green/30 bg-tone-green/10 px-4 py-3 text-sm font-medium text-green-800 lg:mx-8">
                    {{ session('notice') }}
                </div>
            @endif

            <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
