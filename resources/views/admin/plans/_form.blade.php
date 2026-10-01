@php
    $price = $isEdit ? number_format($plan->price_cents / 100, 2, '.', '') : null;
    $setupFee = $isEdit ? number_format($plan->setup_fee_cents / 100, 2, '.', '') : '0';
    $taxLabel = $market->tax_name ?: 'impuesto';
@endphp

<form method="POST" action="{{ $action }}" class="mt-6 space-y-6" novalidate>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Datos del plan</h3>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            @if ($isEdit)
                <div>
                    <p class="block text-sm font-medium text-slate-700">Código</p>
                    <p class="mt-1.5 rounded-lg bg-slate-50 px-3 py-2.5 text-sm text-slate-600">{{ $plan->code }} <span class="text-xs text-slate-400">(no editable)</span></p>
                </div>
            @else
                <x-admin.input name="code" label="Código" :value="null" required placeholder="respaldo"
                               hint="Minúsculas, números, guiones. Único dentro del mercado." />
            @endif

            <x-admin.input name="name" label="Nombre" :value="$plan->name" required placeholder="Respaldo" />

            <x-admin.input name="price" label="Mensualidad (sin impuesto)" type="number" step="0.01" min="0" :value="$price" required
                           hint="En {{ $market->currency }}." />
            <x-admin.input name="setup_fee" label="Configuración inicial (sin impuesto)" type="number" step="0.01" min="0" :value="$setupFee"
                           hint="Cobro único. Usa 0 si no aplica." />

            <x-admin.input name="minutos_mensuales" label="Minutos incluidos al mes" type="number" step="1" min="1" :value="$plan->minutos_mensuales ?: null" required
                           hint="Se copian a la suscripción al comprar; cambiarlos no afecta suscripciones ya vendidas." />
        </div>
    </div>

    {{-- Vista previa del cobro --}}
    <div data-plan-preview data-tax-rate="{{ (float) $market->tax_rate }}" data-currency="{{ $market->currency }}"
         class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
        <h3 class="text-base font-semibold text-slate-900">Lo que paga el cliente</h3>
        <p class="mt-1 text-sm text-slate-500">
            Con {{ $taxLabel }} del {{ rtrim(rtrim(number_format((float) $market->tax_rate, 2), '0'), '.') }}% del mercado {{ $market->name }}.
        </p>
        <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
            <div><dt class="text-slate-500">Mensualidad</dt><dd class="mt-0.5 font-medium text-slate-900" data-monthly>—</dd></div>
            <div><dt class="text-slate-500">Configuración</dt><dd class="mt-0.5 font-medium text-slate-900" data-setup>—</dd></div>
            <div><dt class="text-slate-500">Primer cobro</dt><dd class="mt-0.5 font-semibold text-slate-900" data-first>—</dd></div>
        </dl>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('admin.markets.show', $market->uuid) }}#planes"
           class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Cancelar</a>
        <button type="submit"
                class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
            {{ $isEdit ? 'Guardar cambios' : 'Crear plan' }}
        </button>
    </div>
</form>

@push('scripts')
    <script>
        (() => {
            const root = document.querySelector('[data-plan-preview]');
            const price = document.getElementById('price');
            const setup = document.getElementById('setup_fee');
            if (!root || !price || !setup) return;

            const rate = parseFloat(root.dataset.taxRate) || 0;
            const currency = root.dataset.currency;
            const format = (cents) => (cents / 100).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + currency;

            const update = () => {
                const monthly = Math.round(Math.round((parseFloat(price.value) || 0) * 100) * (1 + rate / 100));
                const setupFee = Math.round(Math.round((parseFloat(setup.value) || 0) * 100) * (1 + rate / 100));

                root.querySelector('[data-monthly]').textContent = format(monthly);
                root.querySelector('[data-setup]').textContent = format(setupFee);
                root.querySelector('[data-first]').textContent = format(monthly + setupFee);
            };

            price.addEventListener('input', update);
            setup.addEventListener('input', update);
            update();
        })();
    </script>
@endpush