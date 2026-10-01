@extends('layouts.admin')

@section('title', 'Agente de voz · '.$organization->name)
@section('heading', 'Prospectos y clientes')

@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            ← {{ $prospect?->business_name ?? 'Prospectos y clientes' }}
        </a>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-2xl font-semibold text-slate-900">Menú y datos del agente</h2>
            <a href="{{ route('web_test.show', $organization->uuid) }}" target="_blank" rel="noopener noreferrer"
               class="text-sm font-medium text-brand-600 hover:text-brand-700">Probar agente (web_test) ↗</a>
        </div>
        <p class="text-sm text-slate-500">Cliente {{ $organization->name }}. Lo que escribas aquí se envía al agente de voz en cada llamada.</p>

        <form method="POST" action="{{ route('admin.organizations.agent.update', $organization->uuid) }}" class="mt-6 space-y-6" novalidate>
            @csrf
            @method('PUT')

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Datos del negocio</h3>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <x-admin.input name="nombre_negocio" label="Nombre que dice el agente" :value="$profile->nombre_negocio"
                                   :placeholder="$organization->name" hint="Si lo dejas vacío usa «{{ $organization->name }}»." />
                    <x-admin.input name="tiempo_preparacion" label="Tiempo de preparación" :value="$profile->tiempo_preparacion" placeholder="veinte minutos" />
                    <div class="sm:col-span-2">
                        <x-admin.input name="direccion" label="Dirección" :value="$profile->direccion" placeholder="Avenida Central doscientos cuarenta y cinco" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-admin.input name="horario" label="Horario" :value="$profile->horario" placeholder="Todos los días, desde las once de la mañana hasta las diez de la noche" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-admin.textarea name="formas_de_pago" label="Formas de pago" :value="$profile->formas_de_pago" rows="3"
                                          hint="Ejemplo: Yape, Plin, efectivo y tarjeta al recoger." />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900">Menú</h3>
                    <button type="button" data-insert-example data-example="{{ $example }}"
                            class="text-sm font-medium text-brand-600 hover:text-brand-700">Insertar ejemplo</button>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Escribe los precios con palabras («22 soles con 90 céntimos») y sin símbolos de moneda, para que el agente los pronuncie bien.
                </p>

                <div class="mt-4">
                    <x-admin.textarea name="menu" label="Platos, precios y disponibilidad" :value="$profile->menu" rows="16"
                                      class="font-mono" hint="Máximo 8,000 caracteres. El menú viaja en cada llamada, así que conviene mantenerlo breve." />
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <x-admin.textarea name="instrucciones_adicionales" label="Instrucciones adicionales (opcional)" :value="$profile->instrucciones_adicionales" rows="4"
                                  hint="Reglas propias de este negocio: promociones vigentes, qué no ofrecen, avisos especiales." />
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}"
                   class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
                <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Guardar</button>
            </div>
        </form>

        {{-- Lo que recibe el agente --}}
        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-6">
            <h3 class="text-base font-semibold text-slate-900">Lo que recibe el agente</h3>
            <p class="mt-1 text-xs text-slate-500">Valores guardados hoy, con los textos de respaldo donde falta algo. Cada nombre es una variable que se usa en el prompt de Retell como <code>&#123;&#123;nombre&#125;&#125;</code>.</p>

            <dl class="mt-4 space-y-4 text-sm">
                @foreach ($variables as $name => $value)
                    <div>
                        <dt class="font-mono text-xs text-brand-600">&#123;&#123;{{ $name }}&#125;&#125;</dt>
                        <dd class="mt-1 max-h-48 overflow-y-auto whitespace-pre-line rounded-lg bg-white px-3 py-2 text-slate-700">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelector('[data-insert-example]')?.addEventListener('click', (event) => {
            const textarea = document.getElementById('menu');
            if (textarea.value.trim() !== '' && !window.confirm('Esto reemplaza el menú actual. ¿Continuar?')) return;
            textarea.value = event.currentTarget.dataset.example;
        });
    </script>
@endpush