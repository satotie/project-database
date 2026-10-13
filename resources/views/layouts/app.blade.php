{{-- Default Livewire layout: staff get the back-office sidebar, everyone else the shop layout. --}}
@if (auth()->user()?->isStaff())
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main class="bg-cream">
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@else
    <x-layouts::shop :title="$title ?? null">
        <div class="shop-container py-8">
            {{ $slot }}
        </div>
    </x-layouts::shop>
@endif
