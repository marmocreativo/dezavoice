@php($isTemplate = $index === '__INDEX__')

<div data-seller-row class="grid items-start gap-3 sm:grid-cols-[1fr_1fr_auto]">
    <div>
        <input type="text" name="new_sellers[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Nombre completo"
               class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
        @unless ($isTemplate)
            @error("new_sellers.{$index}.name")
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        @endunless
    </div>
    <div>
        <input type="email" name="new_sellers[{{ $index }}][email]" value="{{ $row['email'] ?? '' }}" placeholder="correo@ejemplo.com"
               class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40">
        @unless ($isTemplate)
            @error("new_sellers.{$index}.email")
                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
            @enderror
        @endunless
    </div>
    <button type="button" data-remove-row aria-label="Quitar fila"
            class="rounded-lg px-3 py-2.5 text-sm text-slate-400 hover:bg-slate-100 hover:text-slate-700">✕</button>
</div>