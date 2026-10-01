@props(['name', 'label', 'value' => null, 'hint' => null, 'rows' => 4])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->class([
            'mt-1.5 block w-full rounded-lg border px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40',
            'border-red-400' => $errors->has($name),
            'border-slate-300' => ! $errors->has($name),
        ]) }}>{{ old($name, $value) }}</textarea>
    @if ($hint)
        <p class="mt-1.5 text-xs text-slate-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>