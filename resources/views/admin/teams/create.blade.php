@extends('layouts.admin')

@section('title', 'Nuevo equipo')
@section('heading', 'Mercados')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.territories.show', $territory->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $territory->name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Nuevo equipo de venta</h2>
        <p class="text-sm text-slate-500">Territorio {{ $territory->name }} · {{ $territory->market->name }}.</p>

        @include('admin.teams._form', ['isEdit' => false, 'action' => route('admin.territories.teams.store', $territory->uuid)])
    </div>
@endsection