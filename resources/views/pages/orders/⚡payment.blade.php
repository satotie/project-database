<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('แจ้งชำระเงิน')] class extends Component {
    use WithFileUploads;

    public Order $order;

    public $slip;

    public function mount(Order $order): void
    {
        $this->authorize('view', $order);
        $this->order = $order->load(['payments', 'items']);
    }

    public function upload(): void
    {
        $this->authorize('pay', $this->order);

        $this->validate([
            'slip' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:4096'],
        ], [
            'slip.required' => 'กรุณาเลือกไฟล์สลิป',
            'slip.image' => 'ไฟล์ต้องเป็นรูปภาพ',
            'slip.mimes' => 'รองรับเฉพาะไฟล์ JPG หรือ PNG',
            'slip.max' => 'ขนาดไฟล์ต้องไม่เกิน 4MB',
        ]);

        $this->order->payments()->create([
            'slip_path' => $this->slip->store('slips', 'public'),
            'amount' => $this->order->total,
            'status' => PaymentStatus::Pending,
        ]);

        $this->reset('slip');
        $this->order->load('payments');
        Flux::toast(variant: 'success', heading: 'ส่งสลิปเรียบร้อย', text: 'ร้านจะตรวจสอบและยืนยันการชำระเงินโดยเร็ว');
    }
}; ?>

<div class="mx-auto max-w-3xl">
    @if (session('order_placed'))
        <flux:callout variant="success" icon="check-circle" class="mb-6" heading="สั่งซื้อสำเร็จ! หมายเลขคำสั่งซื้อ {{ $order->code }}">
            <flux:callout.text>กรุณาโอนเงินตามยอดด้านล่างและแนบสลิปเพื่อยืนยันการชำระเงิน</flux:callout.text>
        </flux:callout>
    @endif

    <div class="mb-6 flex items-center justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">แจ้งชำระเงิน</flux:heading>
            <flux:text>คำสั่งซื้อ <a href="{{ route('orders.show', $order) }}" wire:navigate class="font-medium text-accent-content hover:underline">{{ $order->code }}</a></flux:text>
        </div>
        <x-status-badge :status="$order->status" />
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="shop-card space-y-4 p-6">
            <flux:heading size="lg">โอนเงินเข้าบัญชี</flux:heading>
            <div class="rounded-xl bg-brown-dark p-5 text-white">
                <p class="text-sm text-zinc-300">ธนาคารกสิกรไทย (ตัวอย่าง)</p>
                <p class="mt-1 font-mono text-2xl tracking-wider">123-4-56789-0</p>
                <p class="mt-1 text-sm">ชื่อบัญชี บจก. ชูส์ ช็อป</p>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-sand p-4">
                <span class="text-zinc-600">ยอดที่ต้องชำระ</span>
                <span class="text-2xl font-semibold text-brown">{{ baht($order->total) }}</span>
            </div>
            <flux:text size="sm">* ข้อมูลบัญชีนี้เป็นข้อมูลสมมติสำหรับการสาธิตเท่านั้น</flux:text>
        </div>

        <div class="shop-card p-6">
            <flux:heading size="lg" class="mb-4">แนบสลิปการโอน</flux:heading>
            @if ($order->canUploadSlip())
                <form wire:submit="upload" class="space-y-4">
                    <flux:input type="file" wire:model="slip" accept="image/png,image/jpeg" label="ไฟล์สลิป (JPG/PNG ไม่เกิน 4MB)" />
                    <div wire:loading wire:target="slip" class="text-sm text-zinc-500">กำลังอัปโหลด...</div>
                    @if ($slip && ! $errors->has('slip'))
                        <img src="{{ $slip->temporaryUrl() }}" alt="ตัวอย่างสลิป" class="max-h-64 rounded-xl border border-zinc-200">
                    @endif
                    <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">ส่งสลิป</flux:button>
                </form>
            @elseif ($order->status === \App\Enums\OrderStatus::PendingPayment)
                <flux:callout icon="clock" color="amber" heading="สลิปของคุณอยู่ระหว่างการตรวจสอบ" />
            @else
                <flux:callout icon="check-circle" color="green" heading="คำสั่งซื้อนี้ไม่ต้องชำระเงินเพิ่มเติม" />
            @endif
        </div>
    </div>

    @if ($order->payments->isNotEmpty())
        <div class="shop-card mt-6 p-6">
            <flux:heading size="lg" class="mb-4">ประวัติการแจ้งชำระเงิน</flux:heading>
            <div class="space-y-3">
                @foreach ($order->payments as $payment)
                    <div class="flex items-center gap-4 rounded-xl border border-zinc-100 p-3" wire:key="pay-{{ $payment->id }}">
                        <a href="{{ $payment->slip_url }}" target="_blank"><img src="{{ $payment->slip_url }}" alt="สลิป" class="size-16 rounded-lg border object-cover"></a>
                        <div class="flex-1 text-sm">
                            <p>{{ thai_date($payment->created_at) }} · {{ baht($payment->amount) }}</p>
                            @if ($payment->reject_reason)
                                <p class="text-red-600">เหตุผล: {{ $payment->reject_reason }}</p>
                            @endif
                        </div>
                        <flux:badge size="sm" :color="$payment->status->color()">{{ $payment->status->label() }}</flux:badge>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-6 flex justify-center gap-3">
        <flux:button :href="route('orders.show', $order)" wire:navigate>ดูรายละเอียดคำสั่งซื้อ</flux:button>
        <flux:button variant="ghost" :href="route('products.index')" wire:navigate>เลือกซื้อสินค้าต่อ</flux:button>
    </div>
</div>
