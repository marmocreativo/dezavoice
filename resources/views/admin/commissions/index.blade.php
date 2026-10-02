@extends('layouts.admin')

@section('title', 'Comisiones por pagar')
@section('heading', 'Comisiones')

@section('content')
    @php
        $roleLabels = ['seller' => 'Vendedor', 'supervisor' => 'Supervisor', 'manager' => 'Gerente'];
        $typeLabels = ['activation' => 'Activación', 'recurring' => 'Recurrente'];
        $money = fn ($cents, $currency) => number_format($cents / 100, 2).' '.$currency;
        $inputClass = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40';
    @endphp

    <div class="mb-5 flex gap-2">
        <span class="rounded-full bg-navy-950 px-4 py-1.5 text-sm font-medium text-white">Por pagar</span>
        <a href="{{ route('admin.commissions.payouts') }}" class="rounded-full bg-white px-4 py-1.5 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Pagos realizados</a>
    </div>

    {{-- Totales --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @forelse ($totals as $currency => $total)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Por pagar ahora ({{ $currency }})</p>
                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $money($total['payable'], $currency) }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">En espera ({{ $currency }})</p>
                <p class="mt-1 text-xl font-semibold text-slate-500">{{ $money($total['waiting'], $currency) }}</p>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2 lg:col-span-4">
                <p class="text-sm text-slate-500">No hay comisiones pendientes de pago.</p>
            </div>
        @endforelse
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('admin.commissions.index') }}" class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar nombre o correo…" class="{{ $inputClass }}">
        <select name="market" class="{{ $inputClass }}">
            <option value="">Todos los mercados</option>
            @foreach ($markets as $market)
                <option value="{{ $market->uuid }}" @selected($filters['market'] === $market->uuid)>{{ $market->name }}</option>
            @endforeach
        </select>
        <select name="role" class="{{ $inputClass }}">
            <option value="">Todos los roles</option>
            @foreach ($roleLabels as $value => $label)
                <option value="{{ $value }}" @selected($filters['role'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Filtrar</button>
            <a href="{{ route('admin.commissions.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Limpiar</a>
        </div>
    </form>

    {{-- Por persona --}}
    <div class="space-y-4">
        @foreach ($groups as $group)
            @php
                $membership = $group['membership'];
                $currency = $group['currency'];
            @endphp

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-base font-semibold text-slate-900">{{ $membership->user?->name ?? 'Sin nombre' }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $roleLabels[$membership->role] ?? $membership->role }}
                            @if ($membership->market) · {{ $membership->market->name }} @endif
                            @if ($membership->status !== 'active') · <span class="text-amber-600">membresía inactiva</span> @endif
                            · {{ $membership->user?->email }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500">Por pagar ahora</p>
                        <p class="text-xl font-semibold {{ $group['payable_cents'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ $money($group['payable_cents'], $currency) }}</p>
                        @if ($group['waiting_cents'] !== 0)
                            <p class="text-xs text-slate-500">+ {{ $money($group['waiting_cents'], $currency) }} en espera</p>
                        @endif
                    </div>
                </div>

                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-medium text-brand-600 hover:text-brand-700">Ver detalle y pagar</summary>

                    <form method="POST" action="{{ route('admin.commissions.pay') }}" class="mt-4" data-pay-form data-currency="{{ $currency }}">
                        @csrf
                        <input type="hidden" name="membership_uuid" value="{{ $membership->uuid }}">
                        <input type="hidden" name="currency" value="{{ $currency }}">

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="w-8 py-2"></th>
                                        <th class="px-3 py-2">Cliente</th>
                                        <th class="px-3 py-2">Concepto</th>
                                        <th class="px-3 py-2">Disponible desde</th>
                                        <th class="px-3 py-2 text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($group['payable'] as $row)
                                        @php $entry = $row['entry']; @endphp
                                        <tr>
                                            <td class="py-2">
                                                <input type="checkbox" name="entries[]" value="{{ $entry->uuid }}" data-cents="{{ $entry->amount_cents }}" checked
                                                       class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                                            </td>
                                            <td class="px-3 py-2 text-slate-800">{{ $entry->sale?->opportunity?->prospect?->business_name ?? '—' }}</td>
                                            <td class="px-3 py-2 text-slate-600">
                                                @if ($entry->reverses_ledger_id)
                                                    <span class="text-red-600">Descuento por reembolso</span>
                                                @else
                                                    {{ $typeLabels[$entry->entry_type] ?? $entry->entry_type }}
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-slate-600">{{ $row['available_on']->format('d/m/Y') }}</td>
                                            <td @class(['px-3 py-2 text-right font-medium', 'text-red-600' => $entry->amount_cents < 0, 'text-slate-900' => $entry->amount_cents >= 0])>{{ $money($entry->amount_cents, $currency) }}</td>
                                        </tr>
                                    @endforeach

                                    @foreach ($group['waiting'] as $row)
                                        @php $entry = $row['entry']; @endphp
                                        <tr class="text-slate-400">
                                            <td class="py-2"></td>
                                            <td class="px-3 py-2">{{ $entry->sale?->opportunity?->prospect?->business_name ?? '—' }}</td>
                                            <td class="px-3 py-2">{{ $typeLabels[$entry->entry_type] ?? $entry->entry_type }} · en espera</td>
                                            <td class="px-3 py-2">{{ $row['available_on']->format('d/m/Y') }}</td>
                                            <td class="px-3 py-2 text-right">{{ $money($entry->amount_cents, $currency) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($group['payable']->isNotEmpty())
                            <div class="mt-5 grid gap-4 sm:grid-cols-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Fecha de pago</label>
                                    <input type="date" name="paid_at" value="{{ now()->toDateString() }}" required class="mt-1.5 {{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Método</label>
                                    <select name="method" class="mt-1.5 {{ $inputClass }}">
                                        @foreach ($methods as $method)
                                            <option value="{{ $method }}">{{ $method }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Referencia</label>
                                    <input type="text" name="reference" maxlength="100" placeholder="N.º de operación" class="mt-1.5 {{ $inputClass }}">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700">Nota</label>
                                    <input type="text" name="note" maxlength="1000" class="mt-1.5 {{ $inputClass }}">
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between gap-4">
                                <p class="text-sm text-slate-600">Total seleccionado: <span class="font-semibold text-slate-900" data-total>—</span></p>
                                <button type="submit" data-pay-button
                                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                                    Marcar como pagado
                                </button>
                            </div>
                        @else
                            <p class="mt-4 text-sm text-slate-500">Todo está en periodo de espera: todavía no hay nada que pagar.</p>
                        @endif
                    </form>
                </details>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-pay-form]').forEach((form) => {
            const total = form.querySelector('[data-total]');
            const button = form.querySelector('[data-pay-button]');
            if (!total || !button) return;

            const sync = () => {
                const cents = [...form.querySelectorAll('input[data-cents]:checked')]
                    .reduce((sum, input) => sum + Number(input.dataset.cents), 0);

                total.textContent = (cents / 100).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + form.dataset.currency;
                total.classList.toggle('text-red-600', cents <= 0);
                button.disabled = cents <= 0;
            };

            form.addEventListener('change', sync);
            form.addEventListener('submit', (event) => {
                if (!window.confirm('¿Registrar este pago? Podrás anularlo después si fue un error.')) event.preventDefault();
            });
            sync();
        });
    </script>
@endpush