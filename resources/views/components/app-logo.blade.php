@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Shoe Shop" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-gold text-brown-dark">
            <x-app-logo-icon class="size-5 fill-current" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="Shoe Shop" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg bg-gold text-brown-dark">
            <x-app-logo-icon class="size-5 fill-current" />
        </x-slot>
    </flux:brand>
@endif
