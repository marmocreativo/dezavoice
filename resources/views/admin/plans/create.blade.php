@extends('layouts.admin')

@section('title', 'Nuevo plan')
@section('heading', 'Mercados')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.markets.show', $market->uuid) }}#planes" class="text-sm text-slate-500 hover:text-slate-700">← {{ $market->name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Nuevo plan</h2>
        <p class="text-sm text-slate-500">Mercado {{ $market->name }} · {{ $market->currency }}.</p>

        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Un plan nuevo no tiene reglas de comisión. Hasta que se definan, las ventas de este plan se registran sin comisión.
        </p>

        @include('admin.plans._form', ['isEdit' => false, 'action' => route('admin.markets.plans.store', $market->uuid)])
    </div>
@endsection