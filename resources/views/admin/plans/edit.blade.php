@extends('layouts.admin')

@section('title', 'Editar '.$plan->name)
@section('heading', 'Mercados')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.markets.show', $market->uuid) }}#planes" class="text-sm text-slate-500 hover:text-slate-700">← {{ $market->name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Editar plan</h2>
        <p class="text-sm text-slate-500">Mercado {{ $market->name }} · {{ $market->currency }}.</p>

        @if ($inUse)
            <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Este plan ya tiene cotizaciones o suscripciones. Los cambios de precio aplican solo a cotizaciones nuevas; lo ya emitido conserva su monto.
            </p>
        @endif

        @include('admin.plans._form', ['isEdit' => true, 'action' => route('admin.plans.update', $plan->uuid)])

        @include('admin.plans._commissions')
    </div>
@endsection