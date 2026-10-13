<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public Order $order;

    public string $trackingNo = '';

    public ?int $rejectingPaymentId = null;

    public string $rejectReason = '';

    public function mount(Order $order): void
    {
        $this->loadOrder($order);
    }

    public function rendering($view): void
    {
        $view->title('คำสั่งซื้อ '.$this->order->code);
    }

    private function loadOrder(Order $order): void
    {
        $this->order = $order->load(['user', 'coupon', 'items.variant.product.images', 'payments.verifier']);
        $this->trackingNo = (string) $this->order->tracking_no;
    }

    public function approve(Payment $payment, OrderService $service): void
    {
        abort_unless($payment->order_id === $this->order->id, 404);
        $service->approvePayment($payment, auth()->user());
        $this->loadOrder($this->order->fresh());
        Flux::toast(variant: 'success', text: 'อนุมัติการชำระเงินแล้ว สถานะเปลี่ยนเป็น "ชำระเงินแล้ว"');
    }

    public function startReject(int $paymentId): void
    {
        $this->rejectingPaymentId = $paymentId;
        $this->rejectReason = '';
        $this->resetErrorBag();
        Flux::modal('reject-payment')->show();
    }

    public function reject(OrderService $service): void
    {
        $this->validate(['rejectReason' => ['required', 'string', 'max:255']], ['rejectReason.required' => 'กรุณาระบุเหตุผลที่ไม่อนุมัติ']);

        $payment = $this->order->payments()->findOrFail($this->rejectingPaymentId);
        $service->rejectPayment($payment, auth()->user(), trim($this->rejectReason));
        $this->loadOrder($this->order->fresh());
        Flux::modal('reject-payment')->close();
        Flux::toast(text: 'ปฏิเสธสลิปแล้ว ลูกค้าสามารถแนบสลิปใหม่ได้');
    }

    public function saveTracking(): void
    {
        $this->validate(['trackingNo' => ['nullable', 'string', 'max:50']]);
        $this->order->update(['tracking_no' => trim($this->trackingNo) ?: null]);
        Flux::toast(variant: 'success', text: 'บันทึกเลขพัสดุแล้ว');
    }

    public function changeStatus(string $status, OrderService $service): void
    {
        $next = OrderStatus::from($status);
        $service->transition($this->order, $next, $this->trackingNo);
        $this->loadOrder($this->order->fresh());
        Flux::modal('confirm-cancel')->close();
        Flux::toast(variant: 'success', text: 'เปลี่ยนสถานะเป็น "'.$next->label().'" แล้ว');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:link :href="route('staff.orders.index')" wire:navigate class="text-sm">← คำสั่งซื้อทั้งหมด</flux:link>
            <div class="mt-1 flex items-center gap-3">
                <flux:heading size="xl" level="1" class="font-mono">{{ $order->code }}</flux:heading>
                <x-status-badge :status="$order->status" />
            </div>
            <flux:text>สั่งซื้อเมื่อ {{ thai_date($order->created_at) }} โดย {{ $order->user->name }} ({{ $order->user->email }})</flux:text>
        </div>

        <div x-data="{ copied: false }">
            <flux:button icon="clipboard-document" data-test="copy-message"
                x-on:click="navigator.clipboard.writeText($refs.msg.value); copied = true; setTimeout(() => copied = false, 2000)">
                <span x-show="!copied">คัดลอกข้อความแจ้งลูกค้า</span>
                <span x-show="copied" x-cloak>คัดลอกแล้ว ✓</span>
            </flux:button>
            <textarea x-ref="msg" class="sr-only" readonly>{{ $order->notificationMessage() }}</textarea>
        </div>
    </div>

    <div class="rounded-xl border border-dashed border-gold bg-white/70 p-4 text-sm text-zinc-700">
        <span class="font-medium text-accent-content">ข้อความที่จะคัดลอก:</span> {{ $order->notificationMessage() }}
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            {{-- Payments --}}
            <section class="shop-card p-5">
                <flux:heading size="lg" class="mb-3">การชำระเงิน</flux:heading>
                @forelse ($order->payments as $payment)
                    <div class="mb-3 flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 sm:flex-row" wire:key="pay-{{ $payment->id }}">
                        <a href="{{ $payment->slip_url }}" target="_blank" class="shrink-0">
                            <img src="{{ $payment->slip_url }}" alt="สลิป" class="h-48 w-36 rounded-lg border bg-zinc-50 object-contain">
                        </a>
                        <div class="flex-1 space-y-1 text-sm">
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" :color="$payment->status->color()">{{ $payment->status->label() }}</flux:badge>
                                <span class="text-zinc-500">แนบเมื่อ {{ thai_date($payment->created_at) }}</span>
                            </div>
                            <p>ยอดที่ต้องชำระ <b>{{ baht($payment->amount) }}</b></p>
                            @if ($payment->verifier)
                                <p class="text-zinc-500">ตรวจสอบโดย {{ $payment->verifier->name }} เมื่อ {{ thai_date($payment->verified_at) }}</p>
                            @endif
                            @if ($payment->reject_reason)
                                <p class="text-red-600">เหตุผล: {{ $payment->reject_reason }}</p>
                            @endif
                            @if ($payment->status === PaymentStatus::Pending && $order->status === OrderStatus::PendingPayment)
                                <div class="flex gap-2 pt-2">
                                    <flux:button size="sm" variant="primary" icon="check" wire:click="approve({{ $payment->id }})" data-test="approve-payment">อนุมัติ</flux:button>
                                    <flux:button size="sm" variant="danger" icon="x-mark" wire:click="startReject({{ $payment->id }})">ไม่อนุมัติ</flux:button>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <flux:text>ลูกค้ายังไม่ได้แนบสลิป</flux:text>
                @endforelse
            </section>

            <x-order-summary :order="$order" />
        </div>

        <div class="space-y-6">
            {{-- Status --}}
            <section class="shop-card space-y-4 p-5">
                <flux:heading size="lg">จัดการสถานะ</flux:heading>

                @if ($order->shipping_method !== ShippingMethod::Pickup && in_array($order->status, [OrderStatus::Paid, OrderStatus::Preparing, OrderStatus::Shipped]))
                    <form wire:submit="saveTracking" class="flex items-end gap-2">
                        <flux:input wire:model="trackingNo" label="เลขพัสดุ" placeholder="เช่น TH1234567890" class="flex-1" />
                        <flux:button type="submit">บันทึก</flux:button>
                    </form>
                    <flux:error name="tracking_no" />
                @endif

                @php $transitions = collect($order->status->allowedTransitions())->reject(fn ($s) => $s === OrderStatus::Cancelled); @endphp
                @if ($transitions->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($transitions as $next)
                            @if ($next === OrderStatus::Paid)
                                <flux:text size="sm">ระบบจะเปลี่ยนเป็น "ชำระเงินแล้ว" เมื่ออนุมัติสลิป หรือกดปุ่มด้านล่างเพื่อยืนยันด้วยตนเอง</flux:text>
                            @endif
                            <flux:button class="w-full" variant="primary" icon="arrow-right" wire:click="changeStatus('{{ $next->value }}')" data-test="to-{{ $next->value }}">
                                เปลี่ยนเป็น "{{ $next->label() }}"
                            </flux:button>
                        @endforeach
                    </div>
                @else
                    <flux:text>คำสั่งซื้อนี้อยู่ในสถานะสุดท้ายแล้ว</flux:text>
                @endif
                <flux:error name="status" />

                @if ($order->status->canTransitionTo(OrderStatus::Cancelled))
                    <flux:modal.trigger name="confirm-cancel">
                        <flux:button class="w-full" variant="danger" icon="x-circle">ยกเลิกคำสั่งซื้อ (คืนสต็อก)</flux:button>
                    </flux:modal.trigger>
                @endif
            </section>

            <x-order-timeline :order="$order" />

            <section class="shop-card p-5">
                <flux:heading size="lg" class="mb-3">ข้อมูลจัดส่ง</flux:heading>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-zinc-500">วิธีจัดส่ง</dt><dd class="font-medium">{{ $order->shipping_method->label() }}</dd></div>
                    <div><dt class="text-zinc-500">ผู้รับ</dt><dd>{{ $order->ship_name }} · {{ $order->ship_phone }}</dd></div>
                    <div><dt class="text-zinc-500">ที่อยู่</dt><dd>{{ $order->ship_address ?? 'รับที่ร้าน' }}</dd></div>
                    <div><dt class="text-zinc-500">เลขพัสดุ</dt><dd class="font-mono">{{ $order->tracking_no ?? '-' }}</dd></div>
                    @if ($order->note)
                        <div><dt class="text-zinc-500">หมายเหตุจากลูกค้า</dt><dd>{{ $order->note }}</dd></div>
                    @endif
                </dl>
            </section>
        </div>
    </div>

    <flux:modal name="reject-payment" class="w-full max-w-md">
        <form wire:submit="reject" class="space-y-4">
            <flux:heading size="lg">ไม่อนุมัติสลิป</flux:heading>
            <flux:textarea wire:model="rejectReason" label="เหตุผล (ลูกค้าจะเห็นข้อความนี้)" rows="3" placeholder="เช่น ยอดเงินไม่ตรงกับคำสั่งซื้อ" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">ยกเลิก</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">ยืนยันไม่อนุมัติ</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-cancel" class="w-full max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">ยกเลิกคำสั่งซื้อ {{ $order->code }}?</flux:heading>
            <flux:text>สินค้าทั้งหมดในคำสั่งซื้อจะถูกคืนเข้าสต็อก</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">ไม่ใช่</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="changeStatus('CANCELLED')">ยืนยันยกเลิก</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
