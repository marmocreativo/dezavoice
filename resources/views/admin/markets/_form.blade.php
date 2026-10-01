<form method="POST" action="{{ $action }}" class="mt-6 space-y-6" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    {{-- Datos del mercado --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Datos del mercado</h3>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            @if ($isEdit)
                <div>
                    <p class="block text-sm font-medium text-slate-700">Código</p>
                    <p class="mt-1.5 rounded-lg bg-slate-50 px-3 py-2.5 text-sm text-slate-600">{{ $market->code }} <span class="text-xs text-slate-400">(no editable)</span></p>
                </div>
            @else
                <x-admin.input name="code" label="Código (2 letras)" :value="$market->code" maxlength="2" required placeholder="MX" class="uppercase" />
            @endif

            <x-admin.input name="name" label="Nombre" :value="$market->name" required placeholder="México" />
            <x-admin.input name="currency" label="Moneda (ISO, 3 letras)" :value="$market->currency" maxlength="3" required placeholder="MXN" class="uppercase" />
            <x-admin.input name="timezone" label="Zona horaria" :value="$market->timezone" list="timezones" required placeholder="America/Mexico_City" />
            <x-admin.input name="tax_name" label="Nombre del impuesto" :value="$market->tax_name" placeholder="IVA" />
            <x-admin.input name="tax_rate" label="Tasa de impuesto (%)" type="number" step="0.01" min="0" max="100" :value="$market->tax_rate" />
        </div>

        <datalist id="timezones">
            @foreach ($timezones as $tz)
                <option value="{{ $tz }}"></option>
            @endforeach
        </datalist>

        <label class="mt-5 flex items-center gap-2 text-sm text-slate-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $market->is_active))
                   class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
            Mercado activo
        </label>
    </div>

    {{-- Gerente --}}
    @php($mode = old('manager_mode', 'keep'))
    <div id="gerente" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Gerente del mercado</h3>
        <p class="mt-1 text-sm text-slate-500">
            Un mercado tiene un gerente. Al cambiarlo, el anterior se retira y su equipo directo pasa al nuevo.
        </p>

        @if ($isEdit && $market->managers->isNotEmpty())
            <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gerente actual</p>
                @foreach ($market->managers as $manager)
                    <p class="mt-1 text-slate-800">{{ $manager->user->name }} <span class="text-slate-500">· {{ $manager->user->email }}</span></p>
                @endforeach
            </div>
        @endif

        <div class="mt-5 space-y-3 text-sm text-slate-700">
            <label class="flex items-center gap-2">
                <input type="radio" name="manager_mode" value="keep" data-manager-mode @checked($mode === 'keep')
                       class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
                {{ $isEdit ? 'Mantener el gerente actual' : 'Asignar gerente después' }}
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" name="manager_mode" value="existing" data-manager-mode @checked($mode === 'existing')
                       class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
                Asignar a una persona que ya existe
            </label>
            <label class="flex items-center gap-2">
                <input type="radio" name="manager_mode" value="new" data-manager-mode @checked($mode === 'new')
                       class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
                Dar de alta a una persona nueva
            </label>
        </div>
        @error('manager_mode')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror

        @if ($isEdit && $market->managers->isNotEmpty())
            <p data-manager-warning class="mt-4 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Al guardar, {{ $market->managers->map(fn ($m) => $m->user->name)->join(', ') }} dejará de ser gerente de este mercado.
            </p>
        @endif

        <div data-manager-panel="existing" class="mt-5 hidden">
            <label for="manager_user_uuid" class="block text-sm font-medium text-slate-700">Persona</label>
            <select id="manager_user_uuid" name="manager_user_uuid"
                    class="mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40 {{ $errors->has('manager_user_uuid') ? 'border-red-400' : 'border-slate-300' }}">
                <option value="">Selecciona una persona…</option>
                @foreach ($candidates as $candidate)
                    <option value="{{ $candidate->uuid }}" @selected(old('manager_user_uuid') === $candidate->uuid)>{{ $candidate->name }} — {{ $candidate->email }}</option>
                @endforeach
            </select>
            @error('manager_user_uuid')
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div data-manager-panel="new" class="mt-5 hidden">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-admin.input name="manager_name" label="Nombre completo" :value="null" />
                <x-admin.input name="manager_email" label="Correo electrónico" type="email" :value="null"
                               hint="Se enviará un correo con una contraseña temporal." />
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ $isEdit ? route('admin.markets.show', $market->uuid) : route('admin.markets.index') }}"
           class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
        <button type="submit"
                class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
            {{ $isEdit ? 'Guardar cambios' : 'Crear mercado' }}
        </button>
    </div>
</form>

@push('scripts')
    <script>
        (() => {
            const radios = document.querySelectorAll('[data-manager-mode]');
            const panels = document.querySelectorAll('[data-manager-panel]');
            const warning = document.querySelector('[data-manager-warning]');

            const sync = () => {
                const mode = document.querySelector('[data-manager-mode]:checked')?.value;
                panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.managerPanel !== mode));
                warning?.classList.toggle('hidden', mode === 'keep');
            };

            radios.forEach((radio) => radio.addEventListener('change', sync));
            sync();
        })();
    </script>
@endpush