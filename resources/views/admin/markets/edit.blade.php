@extends('layouts.admin')

@section('title', 'Editar '.$market->name)
@section('heading', 'Mercados')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.markets.show', $market->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $market->name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Editar mercado</h2>

        @include('admin.markets._form', ['isEdit' => true, 'action' => route('admin.markets.update', $market->uuid)])
    </div>
@endsection