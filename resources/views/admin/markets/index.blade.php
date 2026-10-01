@extends('layouts.admin')

@section('title', 'Mercados')
@section('heading', 'Mercados')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Cada mercado agrupa territorios, equipos de venta y personas.</p>
        <a href="{{ route('admin.markets.create') }}"
           class="inline-flex items-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
            Nuevo mercado
        </a>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Mercado</th>
                    <th class="px-4 py-3">Moneda</th>
                    <th class="px-4 py-3">Impuesto</th>
                    <th class="px-4 py-3">Gerente</th>
                    <th class="px-4 py-3">Territorios</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($markets as $market)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.markets.show', $market->uuid) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $market->name }}</a>
                            <p class="text-xs text-slate-500">{{ $market->code }} · {{ $market->timezone }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $market->currency }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $market->tax_name ? $market->tax_name.' '.(float) $market->tax_rate.'%' : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($market->managers->isNotEmpty())
                                <span class="text-slate-700">{{ $market->managers->map(fn ($m) => $m->user->name)->join(', ') }}</span>
                            @else
                                <span class="text-amber-600">Sin gerente</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $market->territories_count }}</td>
                        <td class="px-4 py-3"><x-admin.badge :active="$market->is_active" /></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-3 text-sm">
                                <a href="{{ route('admin.markets.show', $market->uuid) }}" class="text-slate-600 hover:text-slate-900">Ver</a>
                                <a href="{{ route('admin.markets.edit', $market->uuid) }}" class="text-slate-600 hover:text-slate-900">Editar</a>
                                <form method="POST" action="{{ route('admin.markets.destroy', $market->uuid) }}"
                                      data-confirm="¿Eliminar el mercado {{ $market->name }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-500">Aún no hay mercados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection