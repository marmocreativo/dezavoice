@extends('layouts.admin')

@section('title', $territory->name)
@section('heading', 'Mercados')

@section('content')
    @php
        $sellersCount = $teams->sum(fn ($team) => $team->memberships->count());
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.markets.show', $territory->market->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $territory->market->name }}</a>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ $territory->name }}</h2>
            @if ($territory->geo_reference)
                <p class="mt-1 max-w-2xl text-sm text-slate-500">{{ $territory->geo_reference }}</p>
            @endif
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.territories.edit', $territory->uuid) }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Editar</a>
            <form method="POST" action="{{ route('admin.territories.destroy', $territory->uuid) }}"
                  data-confirm="¿Eliminar el territorio {{ $territory->name }}?">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Eliminar</button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Equipos y vendedores --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-base font-semibold text-slate-900">Equipos de venta <span class="font-normal text-slate-500">({{ $teams->count() }})</span></h3>
                <a href="{{ route('admin.territories.teams.create', $territory->uuid) }}"
                   class="rounded-lg bg-brand-500 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Nuevo equipo</a>
            </div>

            <div class="mt-2 divide-y divide-slate-100">
                @forelse ($teams as $team)
                    <div class="py-4">
                        <div class="flex items-center justify-between gap-3">
                            <a href="{{ route('admin.teams.show', $team->uuid) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $team->name }}</a>
                            <div class="flex items-center gap-4 text-sm text-slate-500">
                                <span>{{ $team->memberships->count() }} {{ $team->memberships->count() === 1 ? 'vendedor' : 'vendedores' }}</span>
                                <x-admin.badge :active="$team->is_active" />
                                <a href="{{ route('admin.teams.edit', $team->uuid) }}" class="text-slate-600 hover:text-slate-900">Editar</a>
                            </div>
                        </div>

                        @if ($team->memberships->isNotEmpty())
                            <ul class="mt-3 space-y-1.5">
                                @foreach ($team->memberships->sortBy(fn ($m) => $m->user->name) as $seller)
                                    <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                        <span class="text-slate-800">{{ $seller->user->name }} <span class="text-slate-500">· {{ $seller->user->email }}</span></span>
                                        <span class="font-mono text-xs text-slate-500">{{ $seller->codigo }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-2 text-sm text-slate-400">Sin vendedores.</p>
                        @endif
                    </div>
                @empty
                    <p class="py-8 text-center text-sm text-slate-500">Este territorio aún no tiene equipos de venta.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            {{-- Supervisor --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Supervisor</h3>

                @forelse ($territory->supervisors as $supervisor)
                    <div class="mt-4 text-sm">
                        <p class="font-medium text-slate-800">{{ $supervisor->user->name }}</p>
                        <p class="text-slate-500">{{ $supervisor->user->email }}</p>
                        <p class="mt-1 text-xs text-slate-400">Código {{ $supervisor->codigo }} · desde {{ $supervisor->started_at?->format('d/m/Y') }}</p>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-amber-600">Sin supervisor asignado.</p>
                @endforelse

                @if ($territory->supervisors->count() > 1)
                    <p class="mt-4 text-xs text-amber-700">Hay más de un supervisor (modelo anterior, uno por equipo). Al cambiarlo se reemplazan todos.</p>
                @endif

                <a href="{{ route('admin.territories.edit', $territory->uuid) }}#supervisor"
                   class="mt-4 inline-block text-sm font-medium text-brand-600 hover:text-brand-700">
                    {{ $territory->supervisors->isEmpty() ? 'Asignar supervisor' : 'Cambiar supervisor' }}
                </a>
            </div>

            {{-- Resumen --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Resumen</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Mercado</dt><dd class="text-slate-800">{{ $territory->market->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Equipos</dt><dd class="text-slate-800">{{ $teams->count() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Vendedores</dt><dd class="text-slate-800">{{ $sellersCount }}</dd></div>
                </dl>
            </div>
        </div>
    </div>
@endsection