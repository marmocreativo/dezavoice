@extends('layouts.admin')

@section('title', 'Editar '.$team->name)
@section('heading', 'Mercados')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('admin.teams.show', $team->uuid) }}" class="text-sm text-slate-500 hover:text-slate-700">← {{ $team->name }}</a>
        <h2 class="mt-1 text-2xl font-semibold text-slate-900">Editar equipo</h2>
        <p class="text-sm text-slate-500">Territorio {{ $territory->name }} · {{ $territory->market->name }}.</p>

        @include('admin.teams._form', ['isEdit' => true, 'action' => route('admin.teams.update', $team->uuid)])
    </div>
@endsection