@extends('layouts.admin')

@section('title', $team->name)
@section('heading', 'Mercados')

@section('content')
    @php
        $territory = $team->territory;
        $currency = $territory->market->currency;
        $money = fn ($cents) => number_format($cents / 100, 2).' '.$currency;
        $totals = $stats['totals'];
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.territories.show', $territory->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">
                ← {{ $territory->market->name }} / {{ $territory->name }}
            </a>
            <div class="mt-1 flex items-center gap-3">
                <h2 class="text-2xl font-semibold text-slate-900">{{ $team->name }}</h2>
                <x-admin.badge :active="$team->is_active" />
            </div>
            <p class="mt-1 text-sm text-slate-500">
                @if ($territory->supervisors->isNotEmpty())
                    Supervisor del territorio: {{ $territory->supervisors->map(fn ($s) => $s->user->name)->join(', ') }}
                @else
                    <span class="text-amber-600">El territorio no tiene supervisor.</span>
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.teams.edit', $team->uuid) }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Editar</a>
            <form method="POST" action="{{ route('admin.teams.destroy', $team->uuid) }}"
                  data-confirm="¿Eliminar el equipo {{ $team->name }}?">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Eliminar</button>
            </form>
        </div>
    </div>

    {{-- Totales del equipo --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['Vendedores', $sellers->count()],
            ['Prospectos', $totals['total']],
            ['% ganados', $totals['won_percent'].'%'],
            ['Ventas', $totals['sales_count']],
            ['Monto vendido', $money($totals['sales_cents'])],
        ] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Vendedores --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Vendedor</th>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3 text-right">Prospectos</th>
                    <th class="px-4 py-3 text-right">Ganados</th>
                    <th class="px-4 py-3 text-right">Perdidos</th>
                    <th class="px-4 py-3 text-right">En proceso</th>
                    <th class="px-4 py-3 text-right">% ganados</th>
                    <th class="px-4 py-3 text-right">Ventas</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sellers as $seller)
                    @php($row = $stats['rows'][$seller->id])
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-900">{{ $seller->user->name }}</p>
                            <p class="text-xs text-slate-500">{{ $seller->user->email }}</p>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $seller->codigo }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ $row['total'] }}</td>
                        <td class="px-4 py-3 text-right text-emerald-700">{{ $row['won'] }}</td>
                        <td class="px-4 py-3 text-right text-red-600">{{ $row['lost'] }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ $row['pending'] }}</td>
                        <td class="px-4 py-3 text-right font-medium text-slate-900">{{ $row['won_percent'] }}%</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ $row['sales_count'] }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ $money($row['sales_cents']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-slate-500">
                            Este equipo aún no tiene vendedores.
                            <a href="{{ route('admin.teams.edit', $team->uuid) }}#vendedores" class="font-medium text-brand-600 hover:text-brand-700">Agregar vendedores</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection