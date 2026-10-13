@props(['order', 'reviewable' => false])
{{-- Items + totals of an order (used by customer and staff order pages). --}}
<div class="shop-card p-5">
    <flux:heading size="lg" class="mb-3">รายการสินค้า</flux:heading>
    <div class="divide-y divide-zinc-100">
        @foreach ($order->items as $item)
            <div class="flex items-center gap-3 py-3" wire:key="oi-{{ $item->id }}">
                <img src="{{ $item->variant?->product?->main_image_url ?? \App\Models\Product::PLACEHOLDER_IMAGE }}" alt="" class="size-14 rounded-lg bg-sand object-cover">
                <div class="min-w-0 flex-1 text-sm">
                    <p class="font-medium text-zinc-900">{{ $item->product_name }}</p>
                    <p class="text-zinc-500">ไซซ์ {{ $item->size }} × {{ $item->quantity }} · {{ baht($item->unit_price) }}</p>
                    @if ($item->discount_amount > 0)
                        <p class="text-green-700">ส่วนลด −{{ baht($item->discount_amount) }}</p>
                    @endif
                    @if ($reviewable && $item->variant?->product)
                        <a href="{{ route('products.show', $item->variant->product) }}#reviews" class="mt-1 inline-flex items-center gap-1 text-accent-content hover:underline">
                            <flux:icon.star variant="micro" /> เขียน/แก้ไขรีวิวสินค้านี้
                        </a>
                    @endif
                </div>
                <span class="font-medium">{{ baht($item->line_total) }}</span>
            </div>
        @endforeach
    </div>
    <dl class="mt-3 space-y-1.5 border-t border-zinc-200 pt-3 text-sm">
        <div class="flex justify-between"><dt class="text-zinc-600">ยอดรวมสินค้า</dt><dd>{{ baht($order->subtotal) }}</dd></div>
        <div class="flex justify-between">
            <dt class="text-zinc-600">ส่วนลด @if ($order->coupon) <span class="font-mono text-xs">({{ $order->coupon->code }} {{ $order->coupon->discount_percent }}%)</span> @endif</dt>
            <dd class="text-green-700">−{{ baht($order->discount_amount) }}</dd>
        </div>
        <div class="flex justify-between"><dt class="text-zinc-600">ค่าจัดส่ง ({{ $order->shipping_method->label() }})</dt><dd>{{ $order->shipping_fee > 0 ? baht($order->shipping_fee) : 'ฟรี' }}</dd></div>
        <div class="flex justify-between pt-1 text-lg font-semibold"><dt>ยอดรวมทั้งหมด</dt><dd class="text-brown">{{ baht($order->total) }}</dd></div>
    </dl>
</div>
