@extends('layouts.admin')

@section('title', 'Pagos de comisiones')
@section('heading', 'Comisiones')

@section('content')
    @php
        $money = fn ($cents, $currency) => number_format($cents / 100, 2).' '.$currency;
        $typeLabels = ['activation' => 'Activación', 'recurring' => 'Recurrente'];
        $inputClass = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40';
    @endphp

    <div class="mb-5 flex gap-2">
        <a href="{{ route('admin.commissions.index') }}" class="rounded-full bg-white px-4 py-1.5 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Por pagar</a>
        <span class="rounded-full bg-navy-950 px-4 py-1.5 text-sm font-medium text-white">Pagos realizados</span>
    </div>

    <form method="GET" action="{{ route('admin.commissions.payouts') }}" class="mb-6 grid gap-3 sm:grid-cols-3">
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar nombre o correo…" class="{{ $inputClass }}">
        <select name="status" class="{{ $inputClass }}">
            <option value="">Pagados y anulados</option>
            <option value="paid" @selected($filters['status'] === 'paid')>Solo pagados</option>
            <option value="voided" @selected($filters['status'] === 'voided')>Solo anulados</option>
        </select>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">Filtrar</button>
            <a href="{{ route('admin.commissions.payouts') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-900">Limpiar</a>
        </div>
    </form>

    <div class="space-y-3">
        @forelse ($payouts as $payout)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm {{ $payout->voided_at ? 'opacity-70' : '' }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $payout->membership?->user?->name ?? 'Sin nombre' }}</p>
                        <p class="text-xs text-slate-500">
                            {{ $payout->paid_at->format('d/m/Y') }}
                            @if ($payout->method) · {{ $payout->method }} @endif
                            @if ($payout->reference) · ref. {{ $payout->reference }} @endif
                            · {{ $payout->entries_count }} {{ $payout->entries_count === 1 ? 'comisión' : 'comisiones' }}
                        </p>
                        @if ($payout->note)<p class="mt-1 text-xs text-slate-500">{{ $payout->note }}</p>@endif
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-semibold {{ $payout->voided_at ? 'text-slate-400 line-through' : 'text-slate-900' }}">{{ $money($payout->total_cents, $payout->currency) }}</p>
                        @if ($payout->voided_at)
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Anulado el {{ $payout->voided_at->format('d/m/Y') }}</span>
                        @else
                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Pagado</span>
                        @endif
                    </div>
                </div>

                @if ($payout->voided_at && $payout->void_reason)
                    <p class="mt-2 text-xs text-red-600">Motivo: {{ $payout->void_reason }}</p>
                @endif

                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-medium text-brand-600 hover:text-brand-700">Ver comisiones incluidas</summary>
                    <ul class="mt-2 divide-y divide-slate-100 text-sm">
                        @foreach ($payout->entries_snapshot ?? [] as $item)
                            <li class="flex items-center justify-between py-1.5">
                                <span class="text-slate-700">
                                    {{ $item['client'] ?? '—' }}
                                    <span class="text-xs text-slate-400">· {{ ($item['reversal'] ?? false) ? 'Descuento por reembolso' : ($typeLabels[$item['type']] ?? $item['type']) }}</span>
                                </span>
                                <span class="{{ $item['amount_cents'] < 0 ? 'text-red-600' : 'text-slate-800' }}">{{ $money($item['amount_cents'], $payout->currency) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>

                @unless ($payout->voided_at)
                    <details class="mt-3">
                        <summary class="cursor-pointer text-sm text-red-600 hover:text-red-800">Anular este pago</summary>
                        <form method="POST" action="{{ route('admin.commissions.void', $payout->uuid) }}" class="mt-2 flex flex-wrap items-end gap-3"
                              data-confirm="¿Anular este pago? Sus comisiones volverán a la lista de pendientes.">
                            @csrf
                            <div class="min-w-[16rem] flex-1">
                                <label class="block text-sm font-medium text-slate-700">Motivo</label>
                                <input type="text" name="reason" required maxlength="500" class="mt-1.5 {{ $inputClass }}">
                            </div>
                            <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Anular pago</button>
                        </form>
                    </details>
                @endunless
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">Todavía no hay pagos registrados.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $payouts->links() }}</div>
@endsection