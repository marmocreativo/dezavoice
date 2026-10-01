@props(['active' => true])

<span {{ $attributes->class([
    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
    'bg-emerald-50 text-emerald-700' => $active,
    'bg-slate-100 text-slate-600' => ! $active,
]) }}>{{ $active ? 'Activo' : 'Inactivo' }}</span>