@props(['used', 'limit'])

@php
    $used = (float) $used;
    $limit = (int) $limit;
    $percent = $limit > 0 ? min(100, (int) round($used / $limit * 100)) : 0;
    $tone = $limit <= 0 ? 'bg-slate-300' : ($used >= $limit ? 'bg-red-500' : ($percent >= 80 ? 'bg-amber-500' : 'bg-emerald-500'));
    $usedLabel = rtrim(rtrim(number_format($used, 2), '0'), '.');
@endphp

<div {{ $attributes }}>
    <div class="flex items-baseline justify-between text-sm">
        <span class="font-medium text-slate-900">{{ $usedLabel === '' ? '0' : $usedLabel }} <span class="font-normal text-slate-500">de {{ number_format($limit) }} min</span></span>
        <span class="text-xs text-slate-500">{{ $limit > 0 ? $percent.'%' : 'Sin límite definido' }}</span>
    </div>
    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full {{ $tone }}" style="width: {{ $percent }}%"></div>
    </div>
</div>