@extends('layouts.admin')

@section('title', 'Nuevo territorio')
@section('heading', 'Mercados')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.markets.show', $market->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $market->name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Nuevo territorio</h2>
        <p class="text-sm text-slate-500">En el mercado {{ $market->name }}.</p>

        @include('admin.territories._form', ['isEdit' => false, 'action' => route('admin.markets.territories.store', $market->uuid)])
    </div>
@endsection