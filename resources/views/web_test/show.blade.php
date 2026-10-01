@extends('layouts.public')

@section('title', 'Prueba de agente de voz')

@section('content')
    <div class="mb-6 flex items-center gap-3">
        <img src="https://www.dezavoice.com/deza-voice-icon.svg" alt="" class="size-9">
        <span class="text-xl font-bold tracking-tight text-white">DEZA <span class="text-brand-500">Voice</span></span>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-xl sm:p-8"
         data-web-test
         data-start-url="{{ route('web_test.start', $organization->uuid) }}"
         data-status-url="{{ route('web_test.status', '__CALL__') }}"
         data-csrf="{{ csrf_token() }}">

        <h1 class="text-xl font-semibold text-slate-900">Prueba de agente de voz</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $organization->name }}</p>

        @if ($subscription)
            <div class="mt-5 rounded-xl border border-slate-200 p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Plan {{ $subscription->plan?->name }}</p>
                <x-admin.usage-bar class="mt-3" :used="$subscription->minutos_utilizados" :limit="$subscription->minutos_mensuales" />
            </div>
        @endif

        @if ($menuMissing)
            <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Este cliente aún no tiene menú configurado: el agente no podrá tomar pedidos.
            </p>
        @endif

        @if ($blockReason)
            <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $blockReason }}</p>
        @else
            <p class="mt-5 text-sm text-slate-600">
                Permite el micrófono y habla con la asistente. Prueba, por ejemplo, pidiendo un cuarto de pollo a la brasa,
                una Inca Kola y dos ajíes adicionales, y confirma el pedido.
            </p>
        @endif

        <p data-error class="mt-4 hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></p>

        <div class="mt-6 flex items-center gap-3">
            <button type="button" data-start @disabled($blockReason)
                    class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                Iniciar llamada
            </button>
            <button type="button" data-stop
                    class="hidden rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-50">
                Terminar llamada
            </button>
            <span data-status class="text-sm text-slate-500">Listo para llamar.</span>
        </div>

        <div data-result class="mt-6 hidden rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resultado</p>
            <p class="mt-2 whitespace-pre-line text-slate-800" data-order></p>
            <dl class="mt-3 grid grid-cols-2 gap-3 text-slate-700">
                <div><dt class="text-xs text-slate-500">Minutos consumidos</dt><dd class="font-medium" data-consumed>—</dd></div>
                <div><dt class="text-xs text-slate-500">Minutos restantes</dt><dd class="font-medium" data-remaining>—</dd></div>
            </dl>
        </div>
    </div>
@endsection