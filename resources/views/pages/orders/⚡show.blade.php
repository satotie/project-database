<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public Order $order;

    public function mount(Order $order): void
    {
        $this->authorize('view', $order);
        $this->loadOrder($order);
    }

    public function rendering($view): void
    {
        $view->title('คำสั่งซื้อ '.$this->order->code);
    }

    private function loadOrder(Order $order): void
    {
        $this->order = $order->load(['items.variant.product.images', 'coupon', 'payments']);
    }

    public function cancel(OrderService $service): void
    {
        $this->authorize('cancel', $this->order);
        $service->cancel($this->order);
        $this->loadOrder($this->order->fresh());
        Flux::modal('cancel-order')->close();
        Flux::toast(variant: 'success', text: 'ยกเลิกคำสั่งซื้อเรียบร้อยแล้ว');
    }
}; ?>

<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:link :href="route('orders.index')" wire:navigate class="text-sm">← คำสั่งซื้อทั้งหมด</flux:link>
            <div class="mt-1 flex items-center gap-3">
                <flux:heading size="xl" level="1" class="font-mono">{{ $order->code }}</flux:heading>
                <x-status-badge :status="$order->status" />
            </div>
            <flux:text>สั่งซื้อเมื่อ {{ thai_date($order->created_at) }}</flux:text>
        </div>
        <div class="flex gap-2">
            @if ($order->canUploadSlip())
                <flux:button variant="primary" icon="banknotes" :href="route('orders.payment', $order)" wire:navigate>แจ้งชำระเงิน</flux:button>
            @elseif ($order->status === OrderStatus::PendingPayment)
                <flux:button icon="clock" :href="route('orders.payment', $order)" wire:navigate>สลิปรอตรวจสอบ</flux:button>
            @endif
            @can('cancel', $order)
                <flux:modal.trigger name="cancel-order">
                    <flux:button variant="danger" icon="x-mark" data-test="cancel-order">ยกเลิกคำสั่งซื้อ</flux:button>
                </flux:modal.trigger>
            @endcan
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            <x-order-summary :order="$order" :reviewable="$order->status === OrderStatus::Delivered" />

            <div class="shop-card p-5">
                <flux:heading size="lg" class="mb-3">การจัดส่ง</flux:heading>
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-zinc-500">วิธีจัดส่ง</dt><dd class="font-medium">{{ $order->shipping_method->label() }}</dd></div>
                    <div><dt class="text-zinc-500">ผู้รับ</dt><dd class="font-medium">{{ $order->ship_name }} · {{ $order->ship_phone }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-zinc-500">ที่อยู่</dt><dd>{{ $order->ship_address ?? 'รับสินค้าที่ร้าน (Shoe Shop สาขาสยาม)' }}</dd></div>
                    <div><dt class="text-zinc-500">เลขพัสดุ</dt><dd class="font-mono font-medium">{{ $order->tracking_no ?? '-' }}</dd></div>
                    @if ($order->note)
                        <div><dt class="text-zinc-500">หมายเหตุ</dt><dd>{{ $order->note }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="space-y-6">
            <x-order-timeline :order="$order" />

            @if ($order->payments->isNotEmpty())
                <div class="shop-card p-5">
                    <flux:heading size="lg" class="mb-3">การชำระเงิน</flux:heading>
                    @foreach ($order->payments as $payment)
                        <div class="flex items-center justify-between py-1.5 text-sm" wire:key="p-{{ $payment->id }}">
                            <span>{{ thai_date($payment->created_at) }}</span>
                            <flux:badge size="sm" :color="$payment->status->color()">{{ $payment->status->label() }}</flux:badge>
                        </div>
                        @if ($payment->reject_reason)
                            <p class="text-xs text-red-600">เหตุผล: {{ $payment->reject_reason }}</p>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <flux:modal name="cancel-order" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">ยืนยันการยกเลิกคำสั่งซื้อ?</flux:heading>
            <flux:text>คำสั่งซื้อ {{ $order->code }} จะถูกยกเลิกและไม่สามารถกู้คืนได้</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">ไม่ใช่ตอนนี้</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="cancel">ยืนยันยกเลิก</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
