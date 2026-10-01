<form method="POST" action="{{ $action }}" class="mt-6 space-y-6" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Datos del equipo --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Datos del equipo</h3>

        <div class="mt-5">
            <x-admin.input name="name" label="Nombre" :value="$team->name" required placeholder="Equipo Centro 1" />
        </div>

        <label class="mt-5 flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $team->is_active))
                   class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
            Equipo activo
        </label>
    </div>

    {{-- Vendedores --}}
    <div id="vendedores" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Vendedores</h3>
        <p class="mt-1 text-sm text-slate-500">
            Los vendedores reportan al supervisor del territorio. Retirar a un vendedor lo desactiva; sus prospectos se quedan con su membresía.
        </p>

        @if ($isEdit)
            <div class="mt-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vendedores actuales ({{ $currentSellers->count() }})</p>

                @forelse ($currentSellers as $seller)
                    <label class="mt-2 flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-800">{{ $seller->user->name }} <span class="text-slate-500">· {{ $seller->user->email }}</span></span>
                        <span class="flex items-center gap-2 text-slate-600">
                            <input type="checkbox" name="remove_sellers[]" value="{{ $seller->uuid }}"
                                   @checked(in_array($seller->uuid, old('remove_sellers', []), true))
                                   class="size-4 rounded border-slate-300 text-red-500 focus:ring-red-500">
                            Retirar
                        </span>
                    </label>
                @empty
                    <p class="mt-2 text-sm text-slate-400">Este equipo no tiene vendedores.</p>
                @endforelse
            </div>
        @endif

        {{-- Personas que ya existen --}}
        <div class="mt-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Agregar personas que ya existen</p>

            @if ($candidates->isEmpty())
                <p class="mt-2 text-sm text-slate-400">No hay otras personas disponibles.</p>
            @else
                <input type="search" data-seller-filter placeholder="Buscar por nombre o correo…"
                       class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40">

                <div class="mt-2 max-h-56 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-2">
                    @foreach ($candidates as $candidate)
                        @php
                            $currentTeam = $candidate->memberships->first()?->salesTeam?->name;
                        @endphp
                        <label data-candidate="{{ mb_strtolower($candidate->name.' '.$candidate->email) }}"
                               class="flex items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-slate-50">
                            <input type="checkbox" name="existing_sellers[]" value="{{ $candidate->uuid }}"
                                   @checked(in_array($candidate->uuid, old('existing_sellers', []), true))
                                   class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                            <span class="text-slate-800">{{ $candidate->name }} <span class="text-slate-500">· {{ $candidate->email }}</span></span>
                            @if ($currentTeam)
                                <span class="ml-auto text-xs text-amber-600">hoy en {{ $currentTeam }} — se moverá</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif
            @error('existing_sellers.*')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Personas nuevas --}}
        <div class="mt-6">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Dar de alta personas nuevas</p>
            <p class="mt-1 text-xs text-slate-500">Recibirán un correo con una contraseña temporal.</p>

            <div data-new-sellers class="mt-3 space-y-3">
                @foreach (old('new_sellers', []) as $i => $row)
                    @include('admin.teams._new-seller-row', ['index' => $i, 'row' => $row])
                @endforeach
            </div>

            <template data-new-seller-template>
                @include('admin.teams._new-seller-row', ['index' => '__INDEX__', 'row' => []])
            </template>

            <button type="button" data-add-seller class="mt-3 text-sm font-medium text-brand-600 hover:text-brand-700">+ Agregar otra persona</button>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ $isEdit ? route('admin.teams.show', $team->uuid) : route('admin.territories.show', $territory->uuid) }}"
           class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
        <button type="submit"
                class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
            {{ $isEdit ? 'Guardar cambios' : 'Crear equipo' }}
        </button>
    </div>
</form>

@push('scripts')
    <script>
        (() => {
            const container = document.querySelector('[data-new-sellers]');
            const template = document.querySelector('[data-new-seller-template]');
            const addButton = document.querySelector('[data-add-seller]');
            let index = container.querySelectorAll('[data-seller-row]').length;

            const addRow = () => {
                container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index++));
            };

            addButton.addEventListener('click', addRow);
            container.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-row]');
                if (button) button.closest('[data-seller-row]').remove();
            });

            if (index === 0) addRow();

            const filter = document.querySelector('[data-seller-filter]');
            filter?.addEventListener('input', () => {
                const query = filter.value.toLowerCase();
                document.querySelectorAll('[data-candidate]').forEach((el) => {
                    el.classList.toggle('hidden', !el.dataset.candidate.includes(query));
                });
            });
        })();
    </script>
@endpush