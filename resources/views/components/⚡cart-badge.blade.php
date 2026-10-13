<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public int $count = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    #[On('cart-updated')]
    public function refreshCount(): void
    {
        $this->count = auth()->user()?->cartCount() ?? 0;
    }
}; ?>

<a href="{{ route('cart') }}" wire:navigate class="relative me-2 inline-flex size-10 items-center justify-center rounded-lg text-zinc-700 hover:bg-sand" aria-label="ตะกร้าสินค้า" data-test="cart-link">
    <flux:icon.shopping-cart class="size-6" />
    @if ($count > 0)
        <span class="absolute -end-0.5 -top-0.5 flex min-w-5 items-center justify-center rounded-full bg-gold px-1 text-xs font-semibold text-brown-dark">{{ $count }}</span>
    @endif
</a>
