@props(['value', 'type' => 'prospect'])

@php
    $maps = [
        'prospect' => [
            'nuevo' => ['Nuevo', 'slate'],
            'calificado' => ['Calificado', 'blue'],
            'demo_programada' => ['Demo programada', 'blue'],
            'demo_realizada' => ['Demo realizada', 'blue'],
            'propuesta_enviada' => ['Propuesta enviada', 'blue'],
            'pago_pendiente' => ['Pago pendiente', 'amber'],
            'venta_ganada' => ['Venta ganada', 'emerald'],
            'activacion' => ['Activación', 'emerald'],
            'cliente_activo' => ['Cliente activo', 'emerald'],
            'no_calificado' => ['No calificado', 'slate'],
            'pausado' => ['Pausado', 'slate'],
            'perdido' => ['Perdido', 'red'],
            'cancelado' => ['Cancelado', 'red'],
            'reembolsado' => ['Reembolsado', 'red'],
        ],
        'subscription' => [
            'pending_payment' => ['Pago pendiente', 'amber'],
            'active' => ['Activa', 'emerald'],
            'cancelled' => ['Cancelada', 'red'],
            'canceled' => ['Cancelada', 'red'],
            'past_due' => ['Vencida', 'red'],
        ],
        'payment' => [
            'pending' => ['Pendiente', 'amber'],
            'confirmed' => ['Confirmado', 'emerald'],
            'refunded' => ['Reembolsado', 'red'],
            'failed' => ['Fallido', 'red'],
        ],
    ];

    $tones = [
        'slate' => 'bg-slate-100 text-slate-700',
        'blue' => 'bg-sky-50 text-sky-700',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'amber' => 'bg-amber-50 text-amber-700',
        'red' => 'bg-red-50 text-red-700',
    ];

    [$label, $tone] = $maps[$type][$value] ?? [\Illuminate\Support\Str::headline((string) $value), 'slate'];
@endphp

<span {{ $attributes->class(['inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium', $tones[$tone]]) }}>{{ $label }}</span>