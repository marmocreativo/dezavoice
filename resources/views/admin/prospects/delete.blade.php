@extends('layouts.admin')

@section('title', 'Eliminar '.$prospect->business_name)
@section('heading', 'Prospectos y clientes')

@section('content')
    @php
        $labels = \App\Services\HardDeleteService::LABELS;
        $label = fn (string $table) => $labels[$table] ?? $table;
    @endphp

    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.prospects.show', $prospect->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $prospect->business_name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-red-700">Eliminar definitivamente</h2>
        <p class="text-sm text-slate-500">{{ $prospect->business_name }}</p>

        <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Esto borra los registros de forma permanente y no se puede deshacer. No cancela nada en Stripe ni libera números en Retell.
            Haz un respaldo de la base de datos antes de continuar.
        </p>

        @if ($plan['blockers'])
            <ul class="mt-4 space-y-1 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                @foreach ($plan['blockers'] as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        @else
            {{-- Alcance --}}
            <form method="GET" action="{{ route('admin.prospects.delete', $prospect->uuid) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <input type="hidden" name="clients" value="0">
                <label class="flex items-start gap-3 text-sm text-slate-700">
                    <input type="checkbox" name="clients" value="1" @checked($includeClients) onchange="this.form.submit()"
                           class="mt-0.5 size-4 rounded border-slate-300 text-red-600 focus:ring-red-500">
                    <span>
                        <span class="font-medium text-slate-900">Incluir también la cuenta del cliente</span><br>
                        <span class="text-xs text-slate-500">
                            Su organización (suscripciones, pagos, mensajes, llamadas, números) y los usuarios cliente que se queden sin ningún rol.
                            @if (! $hasClients) Este prospecto todavía no tiene suscripciones. @endif
                        </span>
                    </span>
                </label>
            </form>

            {{-- Qué se borra --}}
            <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h3 class="text-base font-semibold text-slate-900">Se eliminará</h3>
                </div>
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($plan['counts'] as $table => $count)
                            <tr>
                                <td class="px-5 py-2 text-slate-700">{{ $label($table) }}</td>
                                <td class="px-5 py-2 text-right font-medium text-slate-900">{{ number_format($count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($plan['other_commissions'] > 0)
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    Se perderán {{ number_format($plan['other_commissions']) }} asientos de comisión del <strong>vendedor, su supervisor y su gerente</strong>
                    (las comisiones de las ventas que se borran).
                </p>
            @endif

            @if ($plan['paid_entries'] > 0)
                <p class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ number_format($plan['paid_entries']) }} de esas comisiones ya estaban incluidas en pagos registrados: esos pagos conservarán su total,
                    pero perderán el detalle de esas comisiones.
                </p>
            @endif

            @if ($plan['organizations'])
                <div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <p class="font-medium text-slate-900">Organizaciones cliente que se eliminan</p>
                    <p class="mt-1">{{ implode(', ', $plan['organizations']) }}</p>
                </div>
            @endif

            @if ($plan['extra_users'])
                <div class="mt-4 rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                    <p class="font-medium text-slate-900">Usuarios que también se eliminan</p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach ($plan['extra_users'] as $extra)
                            <li>{{ $extra['name'] }} <span class="text-slate-500">· {{ $extra['email'] }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($plan['detach_counts'])
                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <p class="font-medium text-slate-800">Se conservan, pero pierden la referencia</p>
                    <ul class="mt-1 space-y-0.5 text-xs">
                        @foreach ($plan['detach_counts'] as $key => $count)
                            <li><code>{{ $key }}</code>: {{ number_format($count) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($plan['active_stripe'] > 0)
                <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ $plan['active_stripe'] }} suscripción(es) siguen activas en Stripe. Cancélalas allá primero, o seguirán cobrando.
                </p>
            @endif

            {{-- Confirmación --}}
            <form method="POST" action="{{ route('admin.prospects.destroy', $prospect->uuid) }}" class="mt-6 space-y-4 rounded-2xl border border-red-200 bg-white p-6 shadow-sm" novalidate>
                @csrf
                @method('DELETE')
                <input type="hidden" name="clients" value="{{ $includeClients ? 1 : 0 }}">

                <x-admin.input name="confirm_name" label="Escribe el nombre del negocio para confirmar" :value="null"
                               :placeholder="$prospect->business_name" autocomplete="off" />

                <x-admin.input name="password" label="Tu contraseña de administrador" type="password" :value="null" autocomplete="current-password" />

                @if ($plan['active_stripe'] > 0)
                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="stripe_ack" value="1" class="mt-0.5 size-4 rounded border-slate-300 text-red-600 focus:ring-red-500">
                        Ya cancelé en Stripe las suscripciones activas (o no aplica).
                    </label>
                    @error('stripe_ack')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                @endif

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.prospects.show', $prospect->uuid) }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
                    <button type="submit" class="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700">
                        Eliminar definitivamente
                    </button>
                </div>
            </form>
        @endif
    </div>
@endsection