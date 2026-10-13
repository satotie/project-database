<?php

use App\Enums\ShippingMethod;
use App\Livewire\Forms\AddressForm;
use App\Services\CheckoutService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ชำระเงิน')] class extends Component {
    public string $shippingMethod = 'STANDARD';

    public ?int $addressId = null;

    public string $couponCode = '';

    /** Code that passed validation and is currently applied. */
    public string $appliedCoupon = '';

    public string $note = '';

    public bool $showAddressForm = false;

    public AddressForm $form;

    public function mount()
    {
        if (! auth()->user()->cartItems()->exists()) {
            return $this->redirectRoute('cart', navigate: true);
        }

        $this->addressId = auth()->user()->addresses()->orderByDesc('is_default')->value('id');
        $this->showAddressForm = $this->addressId === null;

        return null;
    }

    #[Computed]
    public function addresses()
    {
        return auth()->user()->addresses()->orderByDesc('is_default')->get();
    }

    #[Computed]
    public function method(): ShippingMethod
    {
        return ShippingMethod::tryFrom($this->shippingMethod) ?? ShippingMethod::Standard;
    }

    #[Computed]
    public function summary(): array
    {
        $service = app(CheckoutService::class);

        return $service->calculate(
            $service->cartLines(auth()->user()),
            $service->findCoupon($this->appliedCoupon),
            $this->method,
        );
    }

    public function applyCoupon(CheckoutService $service): void
    {
        $this->resetErrorBag('couponCode');
        $code = strtoupper(trim($this->couponCode));

        if ($code === '') {
            $this->addError('couponCode', 'กรุณากรอกรหัสคูปอง');

            return;
        }

        $coupon = $service->findCoupon($code);
        if (! $coupon) {
            $this->addError('couponCode', 'ไม่พบรหัสคูปองนี้');

            return;
        }

        $result = $service->calculate($service->cartLines(auth()->user()), $coupon, $this->method);
        if ($result['coupon_error']) {
            $this->addError('couponCode', $result['coupon_error']);

            return;
        }

        $this->appliedCoupon = $code;
        $this->couponCode = $code;
        unset($this->summary);
        Flux::toast(variant: 'success', text: "ใช้คูปอง {$code} ลด ".baht($result['discount']));
    }

    public function removeCoupon(): void
    {
        $this->appliedCoupon = '';
        $this->couponCode = '';
        unset($this->summary);
    }

    public function saveAddress(): void
    {
        $address = $this->form->save(auth()->user());
        $this->addressId = $address->id;
        $this->showAddressForm = false;
        unset($this->addresses);
        Flux::toast(variant: 'success', text: 'บันทึกที่อยู่แล้ว');
    }

    public function placeOrder(CheckoutService $service)
    {
        $this->validate([
            'shippingMethod' => ['required', 'in:STANDARD,EXPRESS,PICKUP'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['shippingMethod.*' => 'กรุณาเลือกวิธีจัดส่ง', 'note.max' => 'หมายเหตุต้องไม่เกิน 255 ตัวอักษร']);

        $address = $this->addressId ? auth()->user()->addresses()->find($this->addressId) : null;

        $order = $service->placeOrder(auth()->user(), $this->method, $address, $this->appliedCoupon ?: null, trim($this->note) ?: null);

        $this->dispatch('cart-updated');
        session()->flash('order_placed', true);

        return $this->redirectRoute('orders.payment', $order, navigate: true);
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="mb-6">ยืนยันคำสั่งซื้อ</flux:heading>

    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            {{-- Shipping method --}}
            <section class="shop-card p-5">
                <flux:heading size="lg" class="mb-4">1. วิธีจัดส่ง</flux:heading>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach (ShippingMethod::cases() as $m)
                        @php($fee = $m->feeFor($this->summary['subtotal']))
                        <label wire:key="sm-{{ $m->value }}" @class([
                            'cursor-pointer rounded-xl border p-4 transition',
                            'border-gold bg-sand/60 ring-1 ring-gold' => $shippingMethod === $m->value,
                            'border-zinc-200 hover:border-gold' => $shippingMethod !== $m->value,
                        ])>
                            <input type="radio" wire:model.live="shippingMethod" value="{{ $m->value }}" class="sr-only">
                            <p class="font-medium text-zinc-900">{{ $m->label() }}</p>
                            <p class="mt-1 text-sm text-brown">
                                {{ $fee > 0 ? baht($fee) : 'ฟรี' }}
                                @if ($m === ShippingMethod::Standard && $fee == 0 && $m->baseFee() > 0)
                                    <span class="text-xs text-zinc-400 line-through">{{ baht($m->baseFee()) }}</span>
                                @endif
                            </p>
                        </label>
                    @endforeach
                </div>
                @if ($this->method === ShippingMethod::Standard && $this->summary['subtotal'] < ShippingMethod::FREE_STANDARD_THRESHOLD)
                    <flux:text size="sm" class="mt-3">ส่งฟรีแบบปกติเมื่อยอดสินค้าครบ {{ baht(ShippingMethod::FREE_STANDARD_THRESHOLD) }}</flux:text>
                @endif
            </section>

            {{-- Address --}}
            @if ($this->method->requiresAddress())
                <section class="shop-card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="lg">2. ที่อยู่จัดส่ง</flux:heading>
                        @if ($this->addresses->isNotEmpty())
                            <flux:button size="sm" variant="ghost" icon="plus" wire:click="$toggle('showAddressForm')">เพิ่มที่อยู่ใหม่</flux:button>
                        @endif
                    </div>
                    <div class="space-y-3">
                        @foreach ($this->addresses as $address)
                            <label wire:key="addr-{{ $address->id }}" @class([
                                'flex cursor-pointer gap-3 rounded-xl border p-4 transition',
                                'border-gold bg-sand/60 ring-1 ring-gold' => $addressId === $address->id,
                                'border-zinc-200 hover:border-gold' => $addressId !== $address->id,
                            ])>
                                <input type="radio" wire:model.live="addressId" value="{{ $address->id }}" class="mt-1 accent-[#c9a063]">
                                <div class="text-sm">
                                    <p class="font-medium text-zinc-900">{{ $address->recipient_name }} · {{ $address->phone }}
                                        @if ($address->is_default) <flux:badge size="sm" color="amber" class="ms-1">ที่อยู่หลัก</flux:badge> @endif
                                    </p>
                                    <p class="text-zinc-600">{{ $address->fullAddress() }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <flux:error name="address" class="mt-2" />

                    @if ($showAddressForm)
                        <form wire:submit="saveAddress" class="mt-4 space-y-4 rounded-xl border border-dashed border-zinc-300 p-4">
                            <flux:heading>ที่อยู่ใหม่</flux:heading>
                            <x-address-fields />
                            <div class="flex gap-2">
                                <flux:button type="submit" variant="primary" size="sm">บันทึกที่อยู่</flux:button>
                                @if ($this->addresses->isNotEmpty())
                                    <flux:button size="sm" variant="ghost" wire:click="$set('showAddressForm', false)">ยกเลิก</flux:button>
                                @endif
                            </div>
                        </form>
                    @endif
                </section>
            @else
                <section class="shop-card p-5">
                    <flux:heading size="lg" class="mb-2">2. รับสินค้าที่ร้าน</flux:heading>
                    <flux:text>Shoe Shop สาขาสยาม ชั้น 2 ห้อง 201 ถนนพระราม 1 เขตปทุมวัน กรุงเทพฯ 10330 (เปิด 10:00–20:00 น.) — เราจะแจ้งเมื่อสินค้าพร้อมให้รับ</flux:text>
                </section>
            @endif

            {{-- Items --}}
            <section class="shop-card p-5">
                <flux:heading size="lg" class="mb-4">3. รายการสินค้า</flux:heading>
                <div class="divide-y divide-zinc-100">
                    @foreach ($this->summary['lines'] as $line)
                        @php($p = $line['variant']->product)
                        <div class="flex items-center gap-3 py-3" wire:key="line-{{ $line['variant']->id }}">
                            <img src="{{ $p->main_image_url }}" alt="" class="size-14 rounded-lg bg-sand object-cover">
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-medium text-zinc-900">{{ $p->name }}</p>
                                <p class="text-zinc-500">ไซซ์ {{ $line['variant']->size }} × {{ $line['quantity'] }} · {{ baht($line['unit_price']) }}</p>
                                @if ($line['discount'] > 0)
                                    <p class="text-green-700">ส่วนลดคูปอง −{{ baht($line['discount']) }}</p>
                                @endif
                            </div>
                            <span class="font-medium">{{ baht($line['line_total']) }}</span>
                        </div>
                    @endforeach
                </div>
                <flux:textarea wire:model="note" label="หมายเหตุถึงร้าน (ไม่บังคับ)" rows="2" class="mt-3" />
            </section>
        </div>

        {{-- Summary --}}
        <aside class="shop-card h-fit space-y-4 p-5 lg:sticky lg:top-20">
            <flux:heading size="lg">สรุปคำสั่งซื้อ</flux:heading>

            <div>
                @if ($appliedCoupon)
                    <div class="flex items-center justify-between rounded-xl border border-green-200 bg-green-50 px-3 py-2 text-sm">
                        <span><flux:icon.ticket class="inline size-4" /> คูปอง <b>{{ $appliedCoupon }}</b> ({{ $this->summary['coupon']?->discount_percent }}%)</span>
                        <flux:button size="xs" variant="ghost" wire:click="removeCoupon">ยกเลิก</flux:button>
                    </div>
                @else
                    <form wire:submit="applyCoupon" class="flex gap-2">
                        <flux:input wire:model="couponCode" placeholder="รหัสคูปอง" class="uppercase" data-test="coupon-input" />
                        <flux:button type="submit">ใช้คูปอง</flux:button>
                    </form>
                @endif
                <flux:error name="couponCode" class="mt-1" />
                <flux:error name="coupon" class="mt-1" />
            </div>

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-zinc-600">ยอดรวมสินค้า</dt><dd>{{ baht($this->summary['subtotal']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-600">ส่วนลดคูปอง</dt><dd class="text-green-700">−{{ baht($this->summary['discount']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-zinc-600">ค่าจัดส่ง</dt><dd>{{ $this->summary['shipping_fee'] > 0 ? baht($this->summary['shipping_fee']) : 'ฟรี' }}</dd></div>
                <div class="flex justify-between border-t border-zinc-200 pt-3 text-lg font-semibold"><dt>ยอดชำระทั้งหมด</dt><dd class="text-brown">{{ baht($this->summary['total']) }}</dd></div>
            </dl>

            <flux:error name="cart" />
            <flux:button wire:click="placeOrder" variant="primary" class="w-full" data-test="place-order">ยืนยันการสั่งซื้อ</flux:button>
            <flux:text size="sm" class="text-center">หลังยืนยัน กรุณาโอนเงินและแนบสลิปเพื่อให้ร้านตรวจสอบ</flux:text>
        </aside>
    </div>
</div>
