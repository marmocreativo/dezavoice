@extends('layouts.admin')

@section('title', 'Editar '.$territory->name)
@section('heading', 'Mercados')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.territories.show', $territory->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $territory->name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Editar territorio</h2>
        <p class="text-sm text-slate-500">Mercado {{ $market->name }}.</p>

        @include('admin.territories._form', ['isEdit' => true, 'action' => route('admin.territories.update', $territory->uuid)])
    </div>
@endsection