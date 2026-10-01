@props(['membership'])

@php
    $labels = [
        'deza_admin' => 'Administrador',
        'manager' => 'Gerente',
        'supervisor' => 'Supervisor',
        'seller' => 'Vendedor',
        'client' => 'Cliente',
    ];

    $scope = match ($membership->role) {
        'manager' => $membership->market?->code,
        'supervisor' => $membership->territory?->name,
        'seller' => $membership->salesTeam?->name,
        default => null,
    };

    $active = $membership->status === 'active';
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium',
    'bg-slate-100 text-slate-700' => $active,
    'bg-slate-50 text-slate-400 line-through' => ! $active,
]) }}>
    {{ $labels[$membership->role] ?? $membership->role }}@if ($scope)<span class="font-normal text-slate-400">· {{ $scope }}</span>@endif
</span>