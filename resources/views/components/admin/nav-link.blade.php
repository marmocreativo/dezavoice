@props(['active' => false])

<a {{ $attributes->class([
    'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
    'bg-white/10 text-white' => $active,
    'text-slate-400 hover:bg-white/5 hover:text-white' => ! $active,
]) }} @if ($active) aria-current="page" @endif>
    @isset($icon)
        <span class="shrink-0 {{ $active ? 'text-brand-500' : 'text-slate-500 group-hover:text-slate-300' }}">
            {{ $icon }}
        </span>
    @endisset
    {{ $slot }}
</a>