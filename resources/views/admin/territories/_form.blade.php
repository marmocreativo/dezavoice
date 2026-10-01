<form method="POST" action="{{ $action }}" class="mt-6 space-y-6" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Datos del territorio</h3>

        <div class="mt-5 space-y-5">
            <x-admin.input name="name" label="Nombre" :value="$territory->name" required placeholder="Norte" />

            <div>
                <label for="geo_reference" class="block text-sm font-medium text-slate-700">
                    Referencia geográfica <span class="font-normal text-slate-400">(opcional)</span>
                </label>
                <textarea id="geo_reference" name="geo_reference" rows="3"
                          class="mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40 {{ $errors->has('geo_reference') ? 'border-red-400' : 'border-slate-300' }}"
                          placeholder="Zonas, ciudades o delegaciones que cubre">{{ old('geo_reference', $territory->geo_reference) }}</textarea>
                @error('geo_reference')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    @include('admin.partials.assign-person', [
        'prefix' => 'supervisor',
        'title' => 'Supervisor del territorio',
        'description' => 'Un territorio tiene un supervisor. Al cambiarlo, el anterior se retira y sus vendedores pasan al nuevo.',
        'currentLabel' => 'Supervisor actual',
        'roleLabel' => 'supervisor',
        'scopeLabel' => 'este territorio',
        'current' => $isEdit ? $territory->supervisors : collect(),
        'candidates' => $candidates,
        'isEdit' => $isEdit,
    ])

    <div class="flex items-center justify-end gap-3">
        <a href="{{ $isEdit ? route('admin.territories.show', $territory->uuid) : route('admin.markets.show', $market->uuid) }}"
           class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
        <button type="submit"
                class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
            {{ $isEdit ? 'Guardar cambios' : 'Crear territorio' }}
        </button>
    </div>
</form>