@props(['icon' => 'inbox', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'shop-card flex flex-col items-center gap-3 px-6 py-14 text-center']) }}>
    <span class="flex size-14 items-center justify-center rounded-full bg-sand text-gold-dark">
        <flux:icon :name="$icon" class="size-7" />
    </span>
    <flux:heading size="lg">{{ $title }}</flux:heading>
    @if ($description)
        <flux:text>{{ $description }}</flux:text>
    @endif
    {{ $slot }}
</div>
