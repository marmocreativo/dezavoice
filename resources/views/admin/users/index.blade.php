@extends('layouts.admin')

@section('title', 'Usuarios')
@section('heading', 'Usuarios')

@section('content')
    @php
        $selectClass = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40';
        $roleOptions = ['deza_admin' => 'Administrador', 'manager' => 'Gerente', 'supervisor' => 'Supervisor', 'seller' => 'Vendedor', 'client' => 'Cliente'];
    @endphp

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar nombre o correo…" class="{{ $selectClass }} lg:col-span-2">

        <select name="role" class="{{ $selectClass }}">
            <option value="">Todos los roles</option>
            @foreach ($roleOptions as $value => $label)
                <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="market" class="{{ $selectClass }}">
            <option value="">Todos los mercados</option>
            @foreach ($markets as $market)
                <option value="{{ $market->uuid }}" @selected($filters['market'] === $market->uuid)>{{ $market->name }}</option>
            @endforeach
        </select>

        <select name="status" class="{{ $selectClass }}">
            <option value="">Con o sin roles activos</option>
            <option value="with_role" @selected($filters['status'] === 'with_role')>Con rol activo</option>
            <option value="no_role" @selected($filters['status'] === 'no_role')>Sin roles activos</option>
        </select>

        <div class="flex gap-2 lg:col-span-5">
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Filtrar</button>
            <a href="{{ route('admin.users.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Limpiar</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3">Roles</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Alta</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.users.show', $user->uuid) }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $user->name }}</a>
                            <p class="text-xs text-slate-500">{{ $user->email }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                @forelse ($user->memberships->sortBy(fn ($m) => $m->status === 'active' ? 0 : 1) as $membership)
                                    <x-admin.role-chip :membership="$membership" />
                                @empty
                                    <span class="text-slate-400">—</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @if ($user->active_roles_count > 0)
                                <x-admin.badge :active="true" />
                            @else
                                <span class="text-xs text-amber-600">Sin roles activos</span>
                            @endif
                            @if ($user->must_change_password)
                                <p class="mt-1 text-xs text-slate-400">Cambio de contraseña pendiente</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">No hay usuarios con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection