@extends('layouts.admin')

@section('title', 'Editar '.$user->name)
@section('heading', 'Usuarios')

@section('content')
    @php
        $mode = old('password_mode', 'keep');
    @endphp

    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.users.show', $user->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $user->name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Editar usuario</h2>

        <form method="POST" action="{{ route('admin.users.update', $user->uuid) }}" class="mt-6 space-y-6" novalidate>
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Datos</h3>
                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-admin.input name="name" label="Nombre" :value="$user->name" required />
                    <x-admin.input name="email" label="Correo electrónico" type="email" :value="$user->email" required
                                   hint="Es con el que inicia sesión." />
                </div>
            </div>

            <div data-password-box class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Contraseña</h3>

                <div class="mt-5 space-y-3 text-sm text-slate-700">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="password_mode" value="keep" data-mode @checked($mode === 'keep')
                               class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
                        Dejar la contraseña como está
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="password_mode" value="generate" data-mode @checked($mode === 'generate')
                               class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
                        Generar una temporal y enviarla por correo
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="password_mode" value="manual" data-mode @checked($mode === 'manual')
                               class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
                        Escribir una contraseña
                    </label>
                </div>
                @error('password_mode')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <div data-panel="manual" class="mt-5 hidden">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-admin.input name="password" label="Nueva contraseña" type="password" :value="null" autocomplete="new-password" hint="Mínimo 8 caracteres." />
                        <x-admin.input name="password_confirmation" label="Confirmar contraseña" type="password" :value="null" autocomplete="new-password" />
                    </div>
                </div>

                <label data-panel="keep" class="mt-5 hidden items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="must_change_password" value="0">
                    <input type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', $user->must_change_password))
                           class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                    Pedir que cambie su contraseña en el próximo acceso
                </label>
                <p data-panel="generate manual" class="mt-5 hidden text-xs text-slate-500">
                    Con una contraseña nueva se le pedirá cambiarla en su próximo acceso.
                </p>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.users.show', $user->uuid) }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
                <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const box = document.querySelector('[data-password-box]');
            const radios = box.querySelectorAll('[data-mode]');
            const panels = box.querySelectorAll('[data-panel]');

            const sync = () => {
                const mode = box.querySelector('[data-mode]:checked')?.value;
                panels.forEach((panel) => {
                    const visible = panel.dataset.panel.split(' ').includes(mode);
                    panel.classList.toggle('hidden', !visible);
                    if (panel.tagName === 'LABEL') panel.classList.toggle('flex', visible);
                });
            };

            radios.forEach((radio) => radio.addEventListener('change', sync));
            sync();
        })();
    </script>
@endpush