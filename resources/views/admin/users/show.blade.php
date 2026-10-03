@extends('layouts.admin')

@section('title', $user->name)
@section('heading', 'Usuarios')

@section('content')
    @php
        $roleLabels = ['deza_admin' => 'Administrador', 'manager' => 'Gerente', 'supervisor' => 'Supervisor', 'seller' => 'Vendedor', 'client' => 'Cliente'];
        $selectClass = 'mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40';
        $memberships = $user->memberships->sortBy(fn ($m) => $m->status === 'active' ? 0 : 1)->values();
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Usuarios</a>
            <h2 class="mt-1 text-2xl font-semibold text-slate-900">{{ $user->name }}</h2>
            <p class="text-sm text-slate-500">{{ $user->email }}</p>
            @if ($user->must_change_password)
                <p class="mt-1 text-xs text-amber-600">Tiene que cambiar su contraseña en el próximo acceso.</p>
            @endif
        </div>

        <a href="{{ route('admin.users.edit', $user->uuid) }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Editar usuario</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Roles --}}
        <div id="roles" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
            <h3 class="text-base font-semibold text-slate-900">Roles <span class="font-normal text-slate-500">({{ $memberships->where('status', 'active')->count() }} activos)</span></h3>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-2 pr-4">Rol</th>
                            <th class="px-4 py-2">Alcance</th>
                            <th class="px-4 py-2">Código</th>
                            <th class="px-4 py-2">Vigencia</th>
                            <th class="py-2 pl-4"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($memberships as $membership)
                            @php
                                $isActive = $membership->status === 'active';
                                $scopeText = match ($membership->role) {
                                    'manager' => $membership->market?->name ?? '—',
                                    'supervisor' => collect([$membership->market?->code, $membership->territory?->name])->filter()->join(' · ') ?: '—',
                                    'seller' => collect([$membership->market?->code, $membership->territory?->name, $membership->salesTeam?->name])->filter()->join(' · ') ?: '—',
                                    'client' => $membership->organization?->name ?? '—',
                                    default => 'Global',
                                };
                            @endphp
                            <tr @class(['text-slate-400' => ! $isActive])>
                                <td class="py-3 pr-4">
                                    <span class="font-medium {{ $isActive ? 'text-slate-900' : '' }}">{{ $roleLabels[$membership->role] ?? $membership->role }}</span>
                                    @unless ($isActive) <span class="ml-1 text-xs">(retirado)</span> @endunless
                                </td>
                                <td class="px-4 py-3">{{ $scopeText }}</td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $membership->codigo ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs">
                                    {{ $membership->started_at?->format('d/m/Y') ?? '—' }}
                                    → {{ $membership->ended_at?->format('d/m/Y') ?? 'hoy' }}
                                </td>
                                <td class="py-3 pl-4 text-right">
                                    @if ($isActive)
                                        <form method="POST" action="{{ route('admin.memberships.retire', $membership->uuid) }}"
                                              data-confirm="¿Retirar el rol de {{ $roleLabels[$membership->role] ?? $membership->role }}? Sus prospectos se quedan con esta asignación y quienes le reportan quedan sin superior hasta que se asigne al siguiente.">
                                            @csrf
                                            <button type="submit" class="text-red-600 hover:text-red-800">Retirar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-500">Este usuario no tiene roles.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Asignar rol --}}
            <div class="mt-8 border-t border-slate-100 pt-6">
                <h4 class="text-sm font-semibold text-slate-900">Asignar rol</h4>
                <p class="mt-1 text-xs text-slate-500">
                    Respeta la jerarquía: un gerente por mercado, un supervisor por territorio, vendedores por equipo.
                    Asignar a alguien donde ya hay una persona la reemplaza.
                </p>

                @if ($errors->any())
                    <ul class="mt-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('admin.users.roles.store', $user->uuid) }}" class="mt-4 space-y-4" novalidate>
                    @csrf

                    <div>
                        <label for="role" class="block text-sm font-medium text-slate-700">Rol</label>
                        <select id="role" name="role" data-role-select class="{{ $selectClass }}">
                            <option value="">Selecciona un rol…</option>
                            <option value="deza_admin" @selected(old('role') === 'deza_admin')>Administrador</option>
                            <option value="manager" @selected(old('role') === 'manager')>Gerente (de un mercado)</option>
                            <option value="supervisor" @selected(old('role') === 'supervisor')>Supervisor (de un territorio)</option>
                            <option value="seller" @selected(old('role') === 'seller')>Vendedor (de un equipo)</option>
                        </select>
                    </div>

                    <div data-role-scope="manager" class="hidden">
                        <label for="market_uuid" class="block text-sm font-medium text-slate-700">Mercado</label>
                        <select id="market_uuid" name="market_uuid" class="{{ $selectClass }}">
                            <option value="">Selecciona un mercado…</option>
                            @foreach ($markets as $market)
                                <option value="{{ $market->uuid }}" @selected(old('market_uuid') === $market->uuid)>{{ $market->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div data-role-scope="supervisor" class="hidden">
                        <label for="territory_uuid" class="block text-sm font-medium text-slate-700">Territorio</label>
                        <select id="territory_uuid" name="territory_uuid" class="{{ $selectClass }}">
                            <option value="">Selecciona un territorio…</option>
                            @foreach ($territories as $territory)
                                <option value="{{ $territory->uuid }}" @selected(old('territory_uuid') === $territory->uuid)>{{ $territory->market?->code }} · {{ $territory->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div data-role-scope="seller" class="hidden">
                        <label for="sales_team_uuid" class="block text-sm font-medium text-slate-700">Equipo</label>
                        <select id="sales_team_uuid" name="sales_team_uuid" class="{{ $selectClass }}">
                            <option value="">Selecciona un equipo…</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->uuid }}" @selected(old('sales_team_uuid') === $team->uuid)>{{ $team->territory?->market?->code }} · {{ $team->territory?->name }} · {{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label data-role-scope="manager supervisor seller" class="hidden items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="replace" value="0">
                        <input type="checkbox" name="replace" value="1" @checked(old('replace', '1') === '1')
                               class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                        Retirar su asignación anterior como ese rol (cambio)
                    </label>

                    <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Asignar rol</button>
                </form>
            </div>
        </div>

        {{-- Datos --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">Cuenta</h3>
                <a href="{{ route('admin.users.delete', $user->uuid) }}" class="text-xs font-medium text-red-600 hover:text-red-800">Eliminar definitivamente…</a>
            </div>

            <form method="POST" action="{{ route('admin.users.password.send', $user->uuid) }}" class="mt-4"
                  data-confirm="¿Enviar una contraseña nueva a {{ $user->email }}? La actual dejará de funcionar y se cerrarán sus sesiones abiertas.">
                @csrf
                <button type="submit" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Enviar contraseña nueva por correo
                </button>
                <p class="mt-1.5 text-xs text-slate-500">Genera una temporal, la envía a su correo y le pide cambiarla al entrar.</p>
            </form>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Correo</dt><dd class="truncate text-slate-800">{{ $user->email }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Alta</dt><dd class="text-slate-800">{{ $user->created_at?->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">Cambio de contraseña</dt><dd class="text-slate-800">{{ $user->must_change_password ? 'Pendiente' : 'No' }}</dd></div>
            </dl>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const select = document.querySelector('[data-role-select]');
            const blocks = document.querySelectorAll('[data-role-scope]');
            if (!select) return;

            const sync = () => {
                blocks.forEach((block) => {
                    const visible = block.dataset.roleScope.split(' ').includes(select.value);
                    block.classList.toggle('hidden', !visible);
                    if (block.tagName === 'LABEL') block.classList.toggle('flex', visible);
                });
            };

            select.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush