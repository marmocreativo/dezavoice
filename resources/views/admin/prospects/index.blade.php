@extends('layouts.admin')

@section('title', 'Prospectos y clientes')
@section('heading', 'Prospectos y clientes')

@section('content')
    @php
        $tabs = ['all' => 'Todos', 'prospects' => 'Prospectos', 'clients' => 'Clientes'];
        $baseQuery = request()->except(['type', 'page']);
        $selectClass = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40';
    @endphp

    {{-- Pestañas --}}
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.prospects.index', $key === 'all' ? $baseQuery : array_merge($baseQuery, ['type' => $key])) }}"
               @class([
                   'rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                   'bg-navy-950 text-white' => $filters['type'] === $key,
                   'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' => $filters['type'] !== $key,
               ])>
                {{ $label }} <span class="opacity-70">({{ $counts[$key] }})</span>
            </a>
        @endforeach
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('admin.prospects.index') }}" class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
        @if ($filters['type'] !== 'all')
            <input type="hidden" name="type" value="{{ $filters['type'] }}">
        @endif

        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar negocio, contacto, correo o teléfono…"
               class="{{ $selectClass }} lg:col-span-2">

        <select name="market" class="{{ $selectClass }}">
            <option value="">Todos los mercados</option>
            @foreach ($markets as $market)
                <option value="{{ $market->uuid }}" @selected($filters['market'] === $market->uuid)>{{ $market->name }}</option>
            @endforeach
        </select>

        <select name="status" class="{{ $selectClass }}">
            <option value="">Todos los estados</option>
            @foreach (\App\Models\SalesProspect::STATUSES as $status)
                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
            @endforeach
        </select>

        <select name="plan" class="{{ $selectClass }}">
            <option value="">Todos los planes</option>
            @foreach ($plans as $plan)
                <option value="{{ $plan->uuid }}" @selected($filters['plan'] === $plan->uuid)>{{ $plan->market?->code }} · {{ $plan->name }}</option>
            @endforeach
        </select>

        <select name="subscription" class="{{ $selectClass }}">
            <option value="">Cualquier suscripción</option>
            <option value="none" @selected($filters['subscription'] === 'none')>Sin suscripción</option>
            @foreach ($subscriptionStatuses as $subscriptionStatus)
                <option value="{{ $subscriptionStatus }}" @selected($filters['subscription'] === $subscriptionStatus)>{{ \Illuminate\Support\Str::headline($subscriptionStatus) }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 lg:col-span-6">
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Filtrar</button>
            <a href="{{ route('admin.prospects.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Limpiar</a>
        </div>
    </form>

    {{-- Listado --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Negocio</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Vendedor</th>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">Suscripción</th>
                    <th class="px-4 py-3">Alta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($prospects as $prospect)
                    @php
                        $subscription = $prospect->currentSubscription();
                        $quotedPlan = $prospect->opportunities->first()?->subscriptionPlan;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.prospects.show', $prospect->uuid) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $prospect->business_name }}</a>
                            <p class="text-xs text-slate-500">{{ $prospect->market?->code }}@if ($prospect->giro) · {{ ucfirst(str_replace('_', ' ', $prospect->giro)) }}@endif</p>
                        </td>
                        <td class="px-4 py-3"><x-admin.status-pill :value="$prospect->status" /></td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $prospect->owner?->user?->name ?? '—' }}
                            @if ($prospect->owner?->codigo)
                                <span class="font-mono text-xs text-slate-400">{{ $prospect->owner->codigo }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($subscription?->plan)
                                <span class="text-slate-800">{{ $subscription->plan->name }}</span>
                            @elseif ($quotedPlan)
                                <span class="text-slate-800">{{ $quotedPlan->name }}</span> <span class="text-xs text-slate-400">cotizado</span>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($subscription)
                                <x-admin.status-pill :value="$subscription->status" type="subscription" />
                                @if ($subscription->status === 'active' && $subscription->current_period_end)
                                    <p class="mt-1 text-xs text-slate-500">Renueva {{ $subscription->current_period_end->format('d/m/Y') }}</p>
                                @elseif ($subscription->canceled_at)
                                    <p class="mt-1 text-xs text-slate-500">Cancelada {{ $subscription->canceled_at->format('d/m/Y') }}</p>
                                @endif
                            @else
                                <span class="text-slate-400">Sin suscripción</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $prospect->created_at->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">No hay resultados con estos filtros.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $prospects->links() }}
    </div>
@endsection