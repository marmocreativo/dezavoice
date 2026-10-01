@extends('layouts.admin')

@section('title', 'Mensajes · '.$prospect->business_name)
@section('heading', 'Prospectos y clientes')

@section('content')
    @php
        $minutes = function ($value) {
            $label = rtrim(rtrim(number_format((float) $value, 2), '0'), '.');

            return $label === '' ? '0' : $label;
        };
        $inputClass = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40';
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.prospects.show', $prospect->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $prospect->business_name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Historial de mensajes</h2>
    </div>

    @if ($subscriptions->isEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
            Este prospecto todavía no tiene suscripción, así que no hay mensajes.
        </div>
    @else
        {{-- Consumo por suscripción --}}
        <div class="mb-6 grid gap-4 md:grid-cols-2">
            @foreach ($subscriptions as $subscription)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $subscription->plan?->name ?? 'Plan eliminado' }}</p>
                            <p class="text-xs text-slate-500">{{ $subscription->organization?->name }}</p>
                        </div>
                        <x-admin.status-pill :value="$subscription->status" type="subscription" />
                    </div>
                    <x-admin.usage-bar class="mt-4" :used="$subscription->minutos_utilizados" :limit="$subscription->minutos_mensuales" />
                    @if ($subscription->current_period_end)
                        <p class="mt-2 text-xs text-slate-400">Periodo actual hasta {{ $subscription->current_period_end->format('d/m/Y') }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('admin.prospects.messages.index', $prospect->uuid) }}" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar en el mensaje…" class="{{ $inputClass }} lg:col-span-2">
            <input type="date" name="from" value="{{ $filters['from'] }}" class="{{ $inputClass }}" aria-label="Desde">
            <input type="date" name="to" value="{{ $filters['to'] }}" class="{{ $inputClass }}" aria-label="Hasta">
            <select name="plan" class="{{ $inputClass }}">
                <option value="">Todos los planes</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->uuid }}" @selected($filters['plan'] === $plan->uuid)>{{ $plan->name }}</option>
                @endforeach
            </select>

            <div class="flex gap-2 lg:col-span-5">
                <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Filtrar</button>
                <a href="{{ route('admin.prospects.messages.index', $prospect->uuid) }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Limpiar</a>
            </div>
        </form>

        <p class="mb-3 text-sm text-slate-500">
            {{ number_format($totals['messages']) }} {{ $totals['messages'] === 1 ? 'mensaje' : 'mensajes' }} · {{ $minutes($totals['minutes']) }} min consumidos
        </p>

        {{-- Mensajes --}}
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3 text-right">Minutos</th>
                        <th class="px-4 py-3">Mensaje</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($messages as $message)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $message->fecha->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $message->plan?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-slate-800">{{ $minutes($message->minutos_consumidos) }}</td>
                            <td class="max-w-xl px-4 py-3 text-slate-700">
                                @if (mb_strlen($message->mensaje) > 160)
                                    <details>
                                        <summary class="cursor-pointer">{{ \Illuminate\Support\Str::limit($message->mensaje, 160) }}</summary>
                                        <p class="mt-2 whitespace-pre-line">{{ $message->mensaje }}</p>
                                    </details>
                                @else
                                    <p class="whitespace-pre-line">{{ $message->mensaje }}</p>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No hay mensajes con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $messages->links() }}
        </div>
    @endif
@endsection