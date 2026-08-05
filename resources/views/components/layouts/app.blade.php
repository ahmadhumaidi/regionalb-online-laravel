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
            <header class="sticky top-0 z-20 border-b border-white/10 bg-black px-4 py-3 text-white shadow-[0_12px_40px_rgba(0,0,0,0.24)] lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" type="button" class="rounded-xl border border-white/15 bg-white/10 p-2 text-slate-300 shadow-sm transition hover:bg-white/15 hover:text-white lg:hidden">
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] text-sky-300 uppercase"><span class="h-1.5 w-1.5 rounded-full bg-emerald-400 shadow-[0_0_0_3px_rgba(52,211,153,0.14)]"></span>{{ $eyebrow }}</div>
                        <h1 class="mt-0.5 text-xl font-bold tracking-tight text-white">{{ $title }}</h1>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    @if (count($allowedRoleKeys ?? []) > 1)
                        <form method="GET" action="{{ url()->current() }}">
                            @foreach (request()->except('role') as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <label class="flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs text-slate-300 shadow-sm">
                                <span class="hidden sm:inline">Tampilan sebagai</span>
                                <select name="role" onchange="this.form.submit()" class="bg-transparent text-sm font-medium text-white focus:outline-none">
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
                            <label class="flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs text-slate-300">
                                <x-icon name="switch" class="h-4 w-4" />
                                <select name="user_id" onchange="this.form.submit()" class="bg-transparent text-sm font-medium text-white focus:outline-none">
                                    @foreach ($impersonationUsers as $candidate)
                                        <option value="{{ $candidate->id }}" @selected($candidate->id === $user->id)>{{ $candidate->name }} &mdash; {{ $candidate->jabatan ?: \App\Support\RsmRole::label($candidate->role) }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </form>
                    @endif

                    <div class="hidden items-center gap-2.5 rounded-xl border border-white/15 bg-white/10 px-2.5 py-1.5 text-right shadow-sm sm:flex">
                        <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-gradient-to-br from-sky-400 to-indigo-500 text-xs font-bold text-white">{{ strtoupper(mb_substr($user->name ?: 'U', 0, 1)) }}</span>
                        <span><p class="text-sm font-semibold leading-tight text-white">{{ $user->name }}</p><p class="mt-0.5 text-[11px] text-slate-300">{{ $user->username }} · {{ $user->jabatan ?: \App\Support\RsmRole::label($user->role) }}</p></span>
                    </div>

                    <button type="button" class="rounded-xl border border-white/15 bg-white/10 p-2.5 text-slate-300 shadow-sm transition hover:bg-white/15 hover:text-white" title="Notifikasi">
                        <x-icon name="bell" class="h-5 w-5" />
                    </button>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/10 px-3 py-2.5 text-sm font-semibold text-slate-200 shadow-sm transition hover:border-red-300/40 hover:bg-red-500/15 hover:text-red-200">
                            <x-icon name="logout" class="h-4 w-4" />
                            <span class="hidden sm:inline">Keluar</span>
                        </button>
                    </form>
                </div>
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
