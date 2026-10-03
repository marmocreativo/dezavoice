@extends('layouts.admin')

@section('title', 'Agente de voz · '.$organization->name)
@section('heading', 'Prospectos y clientes')

@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}" class="text-sm text-slate-500 hover:text-slate-700">
            ← {{ $prospect?->business_name ?? 'Prospectos y clientes' }}
        </a>
        <div class="mt-1 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-2xl font-semibold text-slate-900">Información del negocio para el agente</h2>
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

                    <div>
                        <label for="tipo_solicitud" class="block text-sm font-medium text-slate-700">¿Qué registra el agente?</label>
                        <select id="tipo_solicitud" name="tipo_solicitud"
                                class="mt-1.5 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
                            @foreach ($requestTypes as $value => $type)
                                <option value="{{ $value }}" @selected(old('tipo_solicitud', $profile->tipo_solicitud ?? 'solicitud') === $value)>{{ $type['label'] }}</option>
                            @endforeach
                        </select>
                        @error('tipo_solicitud')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-slate-500">Cómo se llama en los avisos y en el historial del cliente.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <x-admin.textarea name="descripcion" label="¿A qué se dedica?" :value="$profile->descripcion" rows="2"
                                          hint="Una o dos frases. Ejemplo: clínica dental familiar con atención de lunes a sábado." />
                    </div>

                    <div class="sm:col-span-2">
                        <x-admin.input name="direccion" label="Dirección" :value="$profile->direccion" placeholder="Avenida Central doscientos cuarenta y cinco" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-admin.input name="horario" label="Horario" :value="$profile->horario" placeholder="De lunes a sábado, de nueve de la mañana a siete de la noche" />
                    </div>
                    <x-admin.input name="tiempo_preparacion" label="Tiempo de entrega o atención" :value="$profile->tiempo_preparacion" placeholder="veinte minutos"
                                   hint="Preparación, entrega, duración de la cita… lo que aplique." />
                    <x-admin.textarea name="formas_de_pago" label="Formas de pago" :value="$profile->formas_de_pago" rows="2"
                                      hint="Ejemplo: Yape, Plin, efectivo y tarjeta." />
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-slate-900">Productos y servicios</h3>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span class="text-slate-500">Insertar ejemplo:</span>
                        @foreach ($examples as $name => $text)
                            <button type="button" data-insert-example data-example="{{ $text }}"
                                    class="font-medium text-brand-600 hover:text-brand-700">{{ $name }}</button>
                        @endforeach
                    </div>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Lo que ofrece el negocio, con sus precios, condiciones y todo lo que el agente deba saber para responder.
                    Escribe los precios con palabras («22 soles con 90 céntimos») y sin símbolos de moneda, para que los pronuncie bien.
                </p>

                <div class="mt-4">
                    <x-admin.textarea name="menu" label="Catálogo" :value="$profile->menu" rows="16" class="font-mono"
                                      hint="Máximo 8,000 caracteres. Viaja en cada llamada, así que conviene mantenerlo breve." />
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <x-admin.textarea name="instrucciones_adicionales" label="Instrucciones adicionales (opcional)" :value="$profile->instrucciones_adicionales" rows="4"
                                  hint="Reglas propias del negocio: promociones vigentes, qué no ofrecen, solo recoger en tienda, qué datos pedir al cliente…" />
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ $prospect ? route('admin.prospects.show', $prospect->uuid) : route('admin.prospects.index') }}"
                   class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
                <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Guardar</button>
            </div>
        </form>

        {{-- Fotos del catálogo --}}
        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Fotos del catálogo <span class="font-normal text-slate-500">({{ $photos->count() }})</span></h3>
            <p class="mt-1 text-sm text-slate-500">
                Menús, listas de precios o folletos que suben los vendedores desde la app. El agente no lee las fotos: transcribe la información arriba, con precios en palabras.
            </p>

            @if ($photos->isEmpty())
                <p class="mt-4 text-sm text-slate-400">Todavía no hay fotos.</p>
            @else
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($photos as $photo)
                        <a href="{{ $photo->signedUrl() }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-lg border border-slate-200">
                            <img src="{{ $photo->signedUrl() }}" alt="Foto del catálogo" loading="lazy" class="h-32 w-full object-cover">
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

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
        document.querySelectorAll('[data-insert-example]').forEach((button) => {
            button.addEventListener('click', () => {
                const textarea = document.getElementById('menu');
                if (textarea.value.trim() !== '' && !window.confirm('Esto reemplaza el catálogo actual. ¿Continuar?')) return;
                textarea.value = button.dataset.example;
            });
        });
    </script>
@endpush