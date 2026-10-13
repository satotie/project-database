@props(['label', 'value', 'icon' => null, 'hint' => null, 'href' => null])
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'shop-card flex items-start gap-4 p-5'.($href ? ' transition hover:border-gold' : '')]) }}>
    @if ($icon)
        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sand text-gold-dark">
            <flux:icon :name="$icon" class="size-6" />
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-sm text-zinc-500">{{ $label }}</p>
        <p class="mt-0.5 text-2xl font-semibold text-zinc-900">{{ $value }}</p>
        @if ($hint)
            <p class="mt-0.5 text-xs text-zinc-500">{{ $hint }}</p>
        @endif
    </div>
</{{ $href ? 'a' : 'div' }}>
