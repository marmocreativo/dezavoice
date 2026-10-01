@extends('layouts.admin')

@section('title', 'Editar cliente')
@section('heading', 'Prospectos y clientes')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            ← {{ $prospect?->business_name ?? 'Prospectos y clientes' }}
        </a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Editar cliente</h2>
        <p class="text-sm text-slate-500">{{ $organization->name }}</p>

        <form method="POST" action="{{ route('admin.organizations.update', $organization->uuid) }}" class="mt-6 space-y-6" novalidate>
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Datos del cliente</h3>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-admin.input name="name" label="Nombre comercial" :value="$organization->name" required />
                    <x-admin.input name="legal_name" label="Razón social" :value="$organization->legal_name" />
                    <x-admin.input name="tax_id" label="ID fiscal (RFC, RUC, NIF…)" :value="$organization->tax_id" />
                    <x-admin.input name="billing_email" label="Correo de facturación" type="email" :value="$organization->billing_email" />
                    <x-admin.input name="phone" label="Teléfono" :value="$organization->phone" />
                    <x-admin.input name="country" label="País (2 letras)" :value="$organization->country" maxlength="2" class="uppercase" placeholder="MX" />
                    <div class="sm:col-span-2">
                        <x-admin.input name="address_line1" label="Dirección" :value="$organization->address_line1" />
                    </div>
                    <x-admin.input name="city" label="Ciudad" :value="$organization->city" />
                    <x-admin.input name="state" label="Estado / región" :value="$organization->state" />
                    <x-admin.input name="postal_code" label="Código postal" :value="$organization->postal_code" />
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Usuarios con acceso</h3>
                <p class="mt-1 text-sm text-slate-500">
                    El correo del usuario es con el que inicia sesión. Cambiarlo no actualiza el correo registrado en Stripe.
                </p>

                @forelse ($users as $user)
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <x-admin.input :name="'users['.$user->uuid.'][name]'" :dotted="'users.'.$user->uuid.'.name'" label="Nombre" :value="$user->name" required />
                        <x-admin.input :name="'users['.$user->uuid.'][email]'" :dotted="'users.'.$user->uuid.'.email'" label="Correo electrónico" type="email" :value="$user->email" required />
                    </div>
                @empty
                    <p class="mt-4 text-sm text-slate-400">Este cliente no tiene usuarios de acceso.</p>
                @endforelse
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}"
                   class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
                <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection