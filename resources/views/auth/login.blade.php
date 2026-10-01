@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <h1 class="text-xl font-semibold text-slate-900">Iniciar sesión</h1>
    <p class="mt-1 text-sm text-slate-500">Ingresa tus credenciales para continuar.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5" novalidate>
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Correo electrónico</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   class="mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40 {{ $errors->has('email') ? 'border-red-400' : 'border-slate-300' }}">
            @error('email')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">Contraseña</label>
            <input id="password" name="password" type="password"
                   required autocomplete="current-password"
                   class="mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40 {{ $errors->has('password') ? 'border-red-400' : 'border-slate-300' }}">
            @error('password')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
            Mantener sesión iniciada
        </label>

        <button type="submit"
                class="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
            Entrar
        </button>
    </form>
@endsection