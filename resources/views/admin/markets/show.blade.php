@extends('layouts.admin')

@section('title', $market->name)
@section('heading', 'Mercados')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.markets.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Mercados</a>
            <div class="mt-1 flex items-center gap-3">
                <h2 class="text-2xl font-semibold text-slate-900">{{ $market->name }}</h2>
                <x-admin.badge :active="$market->is_active" />
            </div>
            <p class="mt-1 text-sm text-slate-500">{{ $market->code }} · {{ $market->currency }} · {{ $market->timezone }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.markets.edit', $market->uuid) }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Editar</a>
            <form method="POST" action="{{ route('admin.markets.destroy', $market->uuid) }}"
                  data-confirm="¿Eliminar el mercado {{ $market->name }}?">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Eliminar</button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Territorios --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-base font-semibold text-slate-900">Territorios <span class="font-normal text-slate-500">({{ $territories->count() }})</span></h3>
                <a href="{{ route('admin.markets.territories.create', $market->uuid) }}"
                   class="rounded-lg bg-brand-500 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Nuevo territorio</a>
            </div>

            <ul class="mt-4 divide-y divide-slate-100">
                @forelse ($territories as $territory)
                    <li class="flex items-center justify-between gap-4 py-3 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('admin.territories.show', $territory->uuid) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $territory->name }}</a>
                            <p class="truncate text-xs text-slate-500">
                                @if ($territory->supervisors->isNotEmpty())
                                    Supervisor: {{ $territory->supervisors->map(fn ($s) => $s->user->name)->join(', ') }}
                                @else
                                    <span class="text-amber-600">Sin supervisor</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-4 text-slate-500">
                            <span>{{ $territory->sales_teams_count }} {{ $territory->sales_teams_count === 1 ? 'equipo' : 'equipos' }}</span>
                            <span>{{ $territory->sellers_count }} {{ $territory->sellers_count === 1 ? 'vendedor' : 'vendedores' }}</span>
                            <a href="{{ route('admin.territories.edit', $territory->uuid) }}" class="text-slate-600 hover:text-slate-900">Editar</a>
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-slate-500">Este mercado aún no tiene territorios.</li>
                @endforelse
            </ul>
        </div>

        <div class="space-y-6">
            {{-- Gerente --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Gerente</h3>

                @forelse ($market->managers as $manager)
                    <div class="mt-4 text-sm">
                        <p class="font-medium text-slate-800">{{ $manager->user->name }}</p>
                        <p class="text-slate-500">{{ $manager->user->email }}</p>
                        <p class="mt-1 text-xs text-slate-400">Código {{ $manager->codigo }} · desde {{ $manager->started_at?->format('d/m/Y') }}</p>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-amber-600">Sin gerente asignado.</p>
                @endforelse

                <a href="{{ route('admin.markets.edit', $market->uuid) }}#gerente"
                   class="mt-4 inline-block text-sm font-medium text-brand-600 hover:text-brand-700">
                    {{ $market->managers->isEmpty() ? 'Asignar gerente' : 'Cambiar gerente' }}
                </a>
            </div>

            {{-- Datos --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Datos</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Impuesto</dt><dd class="text-slate-800">{{ $market->tax_name ? $market->tax_name.' '.(float) $market->tax_rate.'%' : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Equipos de venta</dt><dd class="text-slate-800">{{ $market->sales_teams_count }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Creado</dt><dd class="text-slate-800">{{ $market->created_at->format('d/m/Y') }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
    {{-- Planes --}}
    @php
        $taxRate = (float) $market->tax_rate;
        $money = fn ($cents) => number_format($cents / 100, 2).' '.$market->currency;
        $withTax = fn ($cents) => (int) round($cents * (1 + $taxRate / 100));
    @endphp

    <div id="planes" class="mt-6 scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <h3 class="text-base font-semibold text-slate-900">Planes <span class="font-normal text-slate-500">({{ $plans->count() }})</span></h3>
            <a href="{{ route('admin.markets.plans.create', $market->uuid) }}"
               class="rounded-lg bg-brand-500 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Nuevo plan</a>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="py-2 pr-4">Plan</th>
                        <th class="px-4 py-2 text-right">Mensualidad</th>
                        <th class="px-4 py-2 text-right">Configuración</th>
                        <th class="px-4 py-2 text-right">Min/mes</th>
                        <th class="px-4 py-2 text-right">Primer cobro{{ $market->tax_name ? ' (c/ '.$market->tax_name.')' : ' (c/ imp.)' }}</th>
                        <th class="px-4 py-2 text-right">Uso</th>
                        <th class="py-2 pl-4"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($plans as $plan)
                        <tr>
                            <td class="py-3 pr-4">
                                <p class="font-medium text-slate-900">{{ $plan->name }}</p>
                                <p class="font-mono text-xs text-slate-500">{{ $plan->code }}</p>
                            </td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ $money($plan->price_cents) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ $money($plan->setup_fee_cents) }}</td>
                            <td class="px-4 py-3 text-right text-slate-700">{{ number_format($plan->minutos_mensuales) }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900">{{ $money($withTax($plan->price_cents) + $withTax($plan->setup_fee_cents)) }}</td>
                            <td class="px-4 py-3 text-right text-xs text-slate-500">
                                {{ $plan->opportunities_count }} cot. · {{ $plan->subscriptions_count }} susc.
                            </td>
                            <td class="py-3 pl-4">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('admin.plans.edit', $plan->uuid) }}" class="text-slate-600 hover:text-slate-900">Editar</a>
                                    <form method="POST" action="{{ route('admin.plans.destroy', $plan->uuid) }}"
                                          data-confirm="¿Eliminar el plan {{ $plan->name }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">Este mercado aún no tiene planes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection