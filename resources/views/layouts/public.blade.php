<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    @include('layouts.partials.head')
    <meta name="robots" content="noindex, nofollow">
</head>
<body class="min-h-full bg-navy-950 font-sans text-slate-800 antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl flex-col justify-center px-4 py-10">
        @yield('content')
    </main>
</body>
</html>