@extends('layouts.admin')

@section('title', 'Números · '.$organization->name)
@section('heading', 'Prospectos y clientes')

@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            ← {{ $prospect?->business_name ?? 'Prospectos y clientes' }}
        </a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Números telefónicos</h2>
        <p class="text-sm text-slate-500">Cliente {{ $organization->name }}. Cuando alguien marque uno de estos números contesta el agente, con el menú y los minutos de este cliente.</p>

        {{-- Configuración en Retell --}}
        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-6">
            <h3 class="text-base font-semibold text-slate-900">Configuración en Retell (una vez por número)</h3>
            <ol class="mt-3 list-decimal space-y-1.5 pl-5 text-sm text-slate-700">
                <li>Compra o importa el número en Retell (Phone Numbers).</li>
                <li>Ábrelo y pega esta URL en <strong>Inbound Webhook URL</strong>.</li>
                <li>No le asignes agente de entrada: el servidor decide qué agente contesta y qué datos usa.</li>
            </ol>
            <div class="mt-3 flex items-center gap-2">
                <code class="flex-1 truncate rounded-lg bg-white px-3 py-2 text-xs text-slate-700" data-inbound-url>{{ $inboundUrl }}</code>
                <button type="button" data-copy class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Copiar</button>
            </div>
        </div>

        {{-- Números --}}
        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Número</th>
                        <th class="px-4 py-3">Línea original</th>
                        <th class="px-4 py-3">Agente</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($numbers as $number)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-mono text-slate-900">{{ $number->phone_number }}</p>
                                @if ($number->label)<p class="text-xs text-slate-500">{{ $number->label }}</p>@endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $number->forwarded_from ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $number->retell_agent_id ?? 'Por defecto' }}</td>
                            <td class="px-4 py-3"><x-admin.badge :active="$number->is_active" /></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('admin.phones.toggle', $number->uuid) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-slate-600 hover:text-slate-900">{{ $number->is_active ? 'Desactivar' : 'Activar' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.phones.destroy', $number->uuid) }}"
                                          data-confirm="¿Eliminar {{ $number->phone_number }}? Las llamadas a ese número se rechazarán.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Este cliente aún no tiene números.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Agregar --}}
        <form method="POST" action="{{ route('admin.organizations.phones.store', $organization->uuid) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" novalidate>
            @csrf
            <h3 class="text-base font-semibold text-slate-900">Asignar un número</h3>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <x-admin.input name="phone_number" label="Número en Retell" placeholder="+12137771235" required hint="Formato internacional, con + y código de país." />
                <x-admin.input name="label" label="Etiqueta (opcional)" placeholder="Línea principal" />
                <x-admin.input name="forwarded_from" label="Línea original del negocio (opcional)" placeholder="+51987654321" hint="La que desvía hacia este número. Solo informativo." />
                <x-admin.input name="retell_agent_id" label="Agente propio (opcional)" placeholder="agent_..." hint="Vacío = usa el agente por defecto." />
                <div class="sm:col-span-2">
                    <x-admin.textarea name="notes" label="Notas (opcional)" rows="2" />
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Asignar número</button>
            </div>
        </form>

        {{-- Últimas llamadas --}}
        <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="px-4 pt-4"><h3 class="text-base font-semibold text-slate-900">Últimas llamadas</h3></div>
            <table class="mt-2 min-w-full divide-y divide-slate-200 text-sm">
                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Fecha</th>
                        <th class="px-4 py-2">Canal</th>
                        <th class="px-4 py-2">Desde → hacia</th>
                        <th class="px-4 py-2">Estado</th>
                        <th class="px-4 py-2 text-right">Minutos</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($calls as $call)
                        <tr>
                            <td class="px-4 py-2 text-slate-600">{{ $call->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $call->canal }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-slate-600">{{ $call->from_number ?? '—' }} → {{ $call->to_number ?? '—' }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $call->status }}</td>
                            <td class="px-4 py-2 text-right text-slate-800">{{ $call->minutos_consumidos !== null ? rtrim(rtrim(number_format((float) $call->minutos_consumidos, 2), '0'), '.') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">Todavía no hay llamadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelector('[data-copy]')?.addEventListener('click', async (event) => {
            const url = document.querySelector('[data-inbound-url]').textContent.trim();
            await navigator.clipboard.writeText(url);
            event.currentTarget.textContent = 'Copiado';
        });
    </script>
@endpush