@php
    $mode = old("{$prefix}_mode", 'keep');
    $hasCurrent = $current->isNotEmpty();
@endphp

<div id="{{ $prefix }}" data-assign class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h3 class="text-base font-semibold text-slate-900">{{ $title }}</h3>
    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>

    @if ($isEdit && $hasCurrent)
        <div class="mt-4 rounded-lg bg-slate-50 p-4 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $currentLabel }}</p>
            @foreach ($current as $membership)
                <p class="mt-1 text-slate-800">{{ $membership->user->name }} <span class="text-slate-500">· {{ $membership->user->email }}</span></p>
            @endforeach
        </div>
    @endif

    <div class="mt-5 space-y-3 text-sm text-slate-700">
        <label class="flex items-center gap-2">
            <input type="radio" name="{{ $prefix }}_mode" value="keep" data-mode @checked($mode === 'keep')
                   class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
            {{ $isEdit && $hasCurrent ? "Mantener al {$roleLabel} actual" : "Asignar {$roleLabel} después" }}
        </label>
        <label class="flex items-center gap-2">
            <input type="radio" name="{{ $prefix }}_mode" value="existing" data-mode @checked($mode === 'existing')
                   class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
            Asignar a una persona que ya existe
        </label>
        <label class="flex items-center gap-2">
            <input type="radio" name="{{ $prefix }}_mode" value="new" data-mode @checked($mode === 'new')
                   class="size-4 border-slate-300 text-brand-500 focus:ring-brand-500">
            Dar de alta a una persona nueva
        </label>
    </div>
    @error("{$prefix}_mode")
        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
    @enderror

    @if ($isEdit && $hasCurrent)
        <p data-warning class="mt-4 hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Al guardar, {{ $current->map(fn ($m) => $m->user->name)->join(', ') }}
            {{ $current->count() > 1 ? 'dejarán' : 'dejará' }} de ser {{ $roleLabel }} de {{ $scopeLabel }}.
        </p>
    @endif

    <div data-panel="existing" class="mt-5 hidden">
        <label for="{{ $prefix }}_user_uuid" class="block text-sm font-medium text-slate-700">Persona</label>
        <select id="{{ $prefix }}_user_uuid" name="{{ $prefix }}_user_uuid"
                class="mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40 {{ $errors->has("{$prefix}_user_uuid") ? 'border-red-400' : 'border-slate-300' }}">
            <option value="">Selecciona una persona…</option>
            @foreach ($candidates as $candidate)
                <option value="{{ $candidate->uuid }}" @selected(old("{$prefix}_user_uuid") === $candidate->uuid)>{{ $candidate->name }} — {{ $candidate->email }}</option>
            @endforeach
        </select>
        @error("{$prefix}_user_uuid")
            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div data-panel="new" class="mt-5 hidden">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.input :name="$prefix.'_name'" label="Nombre completo" :value="null" />
            <x-admin.input :name="$prefix.'_email'" label="Correo electrónico" type="email" :value="null"
                           hint="Se enviará un correo con una contraseña temporal." />
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.querySelectorAll('[data-assign]').forEach((root) => {
                const radios = root.querySelectorAll('[data-mode]');
                const panels = root.querySelectorAll('[data-panel]');
                const warning = root.querySelector('[data-warning]');

                const sync = () => {
                    const mode = root.querySelector('[data-mode]:checked')?.value;
                    panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.panel !== mode));
                    warning?.classList.toggle('hidden', mode === 'keep');
                };

                radios.forEach((radio) => radio.addEventListener('change', sync));
                sync();
            });
        </script>
    @endpush
@endonce