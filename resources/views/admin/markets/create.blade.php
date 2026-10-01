@extends('layouts.admin')

@section('title', 'Nuevo mercado')
@section('heading', 'Mercados')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.markets.index') }}" class="text-sm text-slate-500 hover:text-slate-700">← Mercados</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Nuevo mercado</h2>

        @include('admin.markets._form', ['isEdit' => false, 'action' => route('admin.markets.store')])
    </div>
@endsection