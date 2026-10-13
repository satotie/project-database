<?php

use App\Enums\ShippingMethod;
use App\Models\CartItem;
use App\Services\CartService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ตะกร้าสินค้า')] class extends Component {
    #[Computed]
    public function items()
    {
        return auth()->user()->cartItems()->with(['variant.product.brand', 'variant.product.images'])->latest('id')->get();
    }

    #[Computed]
    public function subtotal(): float
    {
        return $this->items->sum(fn (CartItem $item) => $item->lineTotal());
    }

    #[Computed]
    public function hasStockProblem(): bool
    {
        return $this->items->contains(fn (CartItem $item) => $item->quantity > $item->variant->stock || ! $item->variant->product->is_active);
    }

    public function setQuantity(int $itemId, int $quantity, CartService $cart): void
    {
        $item = auth()->user()->cartItems()->with('variant')->findOrFail($itemId);

        if ($quantity < 1) {
            $this->remove($itemId);

            return;
        }

        $cart->updateQuantity($item, $quantity);
        $this->refreshCart();
    }

    public function remove(int $itemId): void
    {
        auth()->user()->cartItems()->whereKey($itemId)->delete();
        $this->refreshCart();
        Flux::toast(text: 'นำสินค้าออกจากตะกร้าแล้ว');
    }

    private function refreshCart(): void
    {
        unset($this->items, $this->subtotal, $this->hasStockProblem);
        $this->dispatch('cart-updated');
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="mb-6">ตะกร้าสินค้า</flux:heading>

    @if ($this->items->isEmpty())
        <x-empty-state icon="shopping-cart" title="ตะกร้าของคุณยังว่างอยู่" description="เลือกรองเท้าคู่โปรดแล้วกลับมาที่นี่อีกครั้ง">
            <flux:button variant="primary" :href="route('products.index')" wire:navigate>เลือกซื้อสินค้า</flux:button>
        </x-empty-state>
    @else
        <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
            <div class="space-y-3">
                <flux:error name="quantity" />
                @foreach ($this->items as $item)
                    @php($product = $item->variant->product)
                    <div class="shop-card flex gap-4 p-4" wire:key="ci-{{ $item->id }}">
                        <a href="{{ route('products.show', $product) }}" wire:navigate class="size-24 shrink-0 overflow-hidden rounded-xl bg-sand">
                            <img src="{{ $product->main_image_url }}" alt="" class="size-full object-cover">
                        </a>
                        <div class="flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs text-accent-content">{{ $product->brand->name }}</p>
                                <a href="{{ route('products.show', $product) }}" wire:navigate class="font-medium text-zinc-900 hover:underline">{{ $product->name }}</a>
                                <p class="text-sm text-zinc-500">ไซซ์ {{ $item->variant->size }} · {{ baht($product->price) }} / คู่</p>
                                @if (! $product->is_active)
                                    <p class="text-sm text-red-600">สินค้านี้ไม่พร้อมจำหน่ายแล้ว กรุณานำออกจากตะกร้า</p>
                                @elseif ($item->quantity > $item->variant->stock)
                                    <p class="text-sm text-red-600">สต็อกเหลือ {{ $item->variant->stock }} คู่ กรุณาปรับจำนวน</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button size="sm" icon="minus" wire:click="setQuantity({{ $item->id }}, {{ $item->quantity - 1 }})" aria-label="ลดจำนวน" />
                                <span class="w-10 text-center font-medium">{{ $item->quantity }}</span>
                                <flux:button size="sm" icon="plus" wire:click="setQuantity({{ $item->id }}, {{ $item->quantity + 1 }})" aria-label="เพิ่มจำนวน" :disabled="$item->quantity >= $item->variant->stock" />
                            </div>
                            <div class="flex items-center justify-between gap-3 sm:w-36 sm:justify-end">
                                <span class="font-semibold text-brown">{{ baht($item->lineTotal()) }}</span>
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="remove({{ $item->id }})" aria-label="ลบ" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="shop-card h-fit space-y-3 p-5 lg:sticky lg:top-20">
                <flux:heading size="lg">สรุปตะกร้า</flux:heading>
                <div class="flex justify-between text-zinc-600"><span>จำนวนสินค้า</span><span>{{ $this->items->sum('quantity') }} คู่</span></div>
                <div class="flex justify-between text-lg font-semibold"><span>ยอดรวมสินค้า</span><span class="text-brown">{{ baht($this->subtotal) }}</span></div>
                @if ($this->subtotal < ShippingMethod::FREE_STANDARD_THRESHOLD)
                    <flux:text size="sm">ซื้อเพิ่มอีก {{ baht(ShippingMethod::FREE_STANDARD_THRESHOLD - $this->subtotal) }} เพื่อรับสิทธิ์ส่งฟรี (ส่งแบบปกติ)</flux:text>
                @else
                    <flux:text size="sm" class="text-green-700!">คุณได้รับสิทธิ์ส่งฟรี (ส่งแบบปกติ)</flux:text>
                @endif
                <flux:text size="sm">ส่วนลดคูปองและค่าจัดส่งคำนวณในขั้นตอนถัดไป</flux:text>
                <flux:button variant="primary" class="w-full" :href="route('checkout')" wire:navigate :disabled="$this->hasStockProblem" data-test="go-checkout">ดำเนินการสั่งซื้อ</flux:button>
                <flux:button variant="ghost" class="w-full" :href="route('products.index')" wire:navigate>เลือกซื้อสินค้าต่อ</flux:button>
            </div>
        </div>
    @endif
</div>
