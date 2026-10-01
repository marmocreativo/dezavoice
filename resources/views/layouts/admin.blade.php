<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50">
<head>
    @include('layouts.partials.head')
</head>
<body class="h-full font-sans text-slate-800 antialiased">
    @php
        $authUser = auth()->user();
        $initials = \Illuminate\Support\Str::upper(
            \Illuminate\Support\Str::of($authUser->name)
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn ($part) => \Illuminate\Support\Str::substr($part, 0, 1))
                ->implode('')
        );
    @endphp

    <div class="flex min-h-full">
        {{-- Overlay (móvil) --}}
        <div id="sidebar-overlay" class="fixed inset-0 z-30 hidden bg-navy-950/60 lg:hidden"></div>

        {{-- Sidebar --}}
        <aside id="sidebar"
               class="fixed inset-y-0 left-0 z-40 flex w-64 shrink-0 -translate-x-full flex-col bg-navy-950 text-slate-300 transition-transform duration-200 lg:static lg:translate-x-0">
            <div class="flex h-16 shrink-0 items-center gap-2.5 px-6">
                <img src="https://www.dezavoice.com/deza-voice-icon.svg" alt="" class="size-8">
                <span class="text-lg font-bold tracking-tight text-white">DEZA <span class="text-brand-500">Voice</span></span>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <x-admin.nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    <x-slot:icon>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                    </x-slot:icon>
                    Dashboard
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.markets.index')" :active="request()->routeIs('admin.markets.*')">
                    <x-slot:icon>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M3 12h18M12 3c2.5 2.5 3.75 5.5 3.75 9S14.5 18.5 12 21M12 3C9.5 5.5 8.25 8.5 8.25 12S9.5 18.5 12 21" />
                        </svg>
                    </x-slot:icon>
                    Mercados
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.prospects.index')" :active="request()->routeIs('admin.prospects.*')">
                    <x-slot:icon>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </x-slot:icon>
                    Prospectos y clientes
                </x-admin.nav-link>

                <x-admin.nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    <x-slot:icon>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" class="size-5">
                            <circle cx="12" cy="8" r="4" />
                            <path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1" />
                        </svg>
                    </x-slot:icon>
                    Usuarios
                </x-admin.nav-link>
            </nav>
        </aside>

        {{-- Contenido --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-slate-200 bg-white px-4 sm:px-6">
                <button id="sidebar-toggle" type="button" aria-label="Abrir menú"
                        class="-ml-1 rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <h1 class="text-base font-semibold text-slate-900">@yield('heading', 'Panel')</h1>

                <details data-dropdown class="relative ml-auto">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                        <span class="flex size-8 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">{{ $initials }}</span>
                        <span class="hidden text-sm font-medium text-slate-700 sm:block">{{ $authUser->name }}</span>
                    </summary>

                    <div class="absolute right-0 mt-2 w-60 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                        <div class="border-b border-slate-100 px-3 py-2">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $authUser->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $authUser->email }}</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="mt-1 w-full rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100">
                                Cerrar sesión
                            </button>
                        </form>
                    </div>
                </details>
            </header>

            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <x-admin.flash />
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (() => {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const toggle = document.getElementById('sidebar-toggle');

            const setOpen = (open) => {
                sidebar.classList.toggle('-translate-x-full', !open);
                overlay.classList.toggle('hidden', !open);
            };

            toggle?.addEventListener('click', () => setOpen(sidebar.classList.contains('-translate-x-full')));
            overlay?.addEventListener('click', () => setOpen(false));

            document.addEventListener('click', (event) => {
                document.querySelectorAll('details[data-dropdown][open]').forEach((d) => {
                    if (!d.contains(event.target)) d.removeAttribute('open');
                });
            });
        })();
    </script>
    <script>
        // Confirmación genérica: <form data-confirm="¿Seguro?">
        document.addEventListener('submit', (event) => {
            const message = event.target.dataset?.confirm;
            if (message && !window.confirm(message)) event.preventDefault();
        });
    </script>
    @stack('scripts')
</body>
</html>