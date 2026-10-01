@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'dotted' => null])

@php
    $key = $dotted ?? $name;
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($key, $value) }}"
        {{ $attributes->class([
            'mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40',
            'border-red-400' => $errors->has($key),
            'border-slate-300' => ! $errors->has($key),
        ]) }}>
    @if ($hint)
        <p class="mt-1.5 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($key)
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>