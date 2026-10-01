<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-full bg-navy-950 font-sans text-slate-800 antialiased">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <div class="mb-8 flex items-center gap-3">
            <img src="https://www.dezavoice.com/deza-voice-icon.svg" alt="" class="size-9">
            <span class="text-xl font-bold tracking-tight text-white">DEZA <span class="text-brand-500">Voice</span></span>
        </div>

        <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
            @yield('content')
        </div>

        <p class="mt-6 text-xs text-slate-500">© {{ date('Y') }} DEZA Voice</p>
    </div>
</body>
</html>