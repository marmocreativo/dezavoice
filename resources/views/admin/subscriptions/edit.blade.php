@extends('layouts.admin')

@section('title', 'Editar plan y suscripción')
@section('heading', 'Prospectos y clientes')

@section('content')
    @php
        $selectClass = 'mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40';
        $dateValue = fn ($value) => $value ? $value->format('Y-m-d') : null;
    @endphp

    <div class="mx-auto max-w-3xl">
        <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            ← {{ $prospect?->business_name ?? 'Prospectos y clientes' }}
        </a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Editar plan y suscripción</h2>
        <p class="text-sm text-slate-500">{{ $subscription->organization?->name }}</p>

        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Esto cambia los datos locales. No modifica la suscripción en Stripe ni recalcula pagos o comisiones ya generados.
            Después de cambiar el plan o el estado, usa "Revisar comisiones" en el detalle.
        </p>

        <form method="POST" action="{{ route('admin.subscriptions.update', $subscription->uuid) }}" class="mt-6 space-y-6" novalidate>
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="plan_uuid" class="block text-sm font-medium text-slate-700">Plan</label>
                        <select id="plan_uuid" name="plan_uuid" class="{{ $selectClass }} {{ $errors->has('plan_uuid') ? 'border-red-400' : '' }}">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->uuid }}" @selected(old('plan_uuid', $subscription->plan?->uuid) === $plan->uuid)>
                                    {{ $plan->name }} — {{ number_format($plan->price_cents / 100, 2) }} {{ $plan->currency }}
                                </option>
                            @endforeach
                        </select>
                        @error('plan_uuid')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700">Estado</label>
                        <select id="status" name="status" class="{{ $selectClass }} {{ $errors->has('status') ? 'border-red-400' : '' }}">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $subscription->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-admin.input name="started_at" label="Inicio" type="date" :value="$dateValue($subscription->started_at)" />
                    <x-admin.input name="current_period_end" label="Fin del periodo actual" type="date" :value="$dateValue($subscription->current_period_end)" />
                    <x-admin.input name="canceled_at" label="Fecha de cancelación" type="date" :value="$dateValue($subscription->canceled_at)"
                                   hint="Solo aplica si el estado es Cancelada; si la dejas vacía se usa hoy." />

                    <x-admin.input name="minutos_mensuales" label="Minutos incluidos al mes" type="number" step="1" min="0" :value="$subscription->minutos_mensuales" required
                                   hint="Se copian del plan al comprar. Si cambias el plan, toma los del nuevo plan." />
                    <x-admin.input name="minutos_utilizados" label="Minutos utilizados en el periodo" type="number" step="0.01" min="0" :value="$subscription->minutos_utilizados" required
                                   hint="Se reinicia con cada pago mensual confirmado." />

                    <div>
                        <p class="block text-sm font-medium text-slate-700">Stripe</p>
                        <p class="mt-1.5 truncate rounded-lg bg-slate-50 px-3 py-2.5 font-mono text-xs text-slate-600">{{ $subscription->stripe_subscription_id ?? 'Sin suscripción en Stripe' }}</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}"
                   class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
                <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection