@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h2 class="text-2xl font-semibold text-slate-900">¡Bienvenido, {{ $firstName }}!</h2>
        <p class="mt-2 text-slate-500">Este es el panel de administración de DEZA Voice.</p>
    </div>
@endsection