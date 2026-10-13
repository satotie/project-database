<?php

use App\Concerns\ProfileValidationRules;
use App\Enums\OrderStatus;
use App\Livewire\Forms\AddressForm;
use App\Models\Address;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('ข้อมูลสมาชิก')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public AddressForm $form;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
    }

    #[Computed]
    public function stats(): array
    {
        $orders = auth()->user()->orders();

        return [
            'orders' => (clone $orders)->count(),
            'spent' => (float) (clone $orders)->whereIn('status', OrderStatus::revenueValues())->sum('total'),
            'pending' => (clone $orders)->where('status', OrderStatus::PendingPayment)->count(),
        ];
    }

    #[Computed]
    public function addresses()
    {
        return auth()->user()->addresses()->orderByDesc('is_default')->latest('id')->get();
    }

    public function saveProfile(): void
    {
        $user = auth()->user();
        $data = $this->validate([
            ...$this->profileRules($user->id),
            'phone' => ['nullable', 'regex:/^[0-9+\-\s]{9,20}$/'],
        ], [], ['name' => 'ชื่อ', 'email' => 'อีเมล', 'phone' => 'เบอร์โทรศัพท์']);

        $user->update([...$data, 'phone' => $data['phone'] ?: null]);
        Flux::toast(variant: 'success', text: 'บันทึกข้อมูลส่วนตัวแล้ว');
    }

    public function newAddress(): void
    {
        $this->form->reset();
        $this->resetErrorBag();
        Flux::modal('address-form')->show();
    }

    public function editAddress(Address $address): void
    {
        $this->authorize('update', $address);
        $this->resetErrorBag();
        $this->form->setAddress($address);
        Flux::modal('address-form')->show();
    }

    public function saveAddress(): void
    {
        if ($this->form->address) {
            $this->authorize('update', $this->form->address);
        }

        $this->form->save(auth()->user());
        unset($this->addresses);
        Flux::modal('address-form')->close();
        Flux::toast(variant: 'success', text: 'บันทึกที่อยู่แล้ว');
    }

    public function makeDefault(Address $address): void
    {
        $this->authorize('update', $address);
        auth()->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);
        unset($this->addresses);
    }

    public function deleteAddress(Address $address): void
    {
        $this->authorize('delete', $address);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            auth()->user()->addresses()->latest('id')->first()?->update(['is_default' => true]);
        }

        unset($this->addresses);
        Flux::toast(text: 'ลบที่อยู่แล้ว');
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="mb-6">ข้อมูลสมาชิก</flux:heading>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="shop-card flex items-center gap-4 p-5 lg:col-span-1">
            <flux:avatar size="lg" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
            <div>
                <p class="font-medium text-zinc-900">{{ auth()->user()->name }}</p>
                <p class="text-xs text-zinc-500">สมาชิกตั้งแต่ {{ thai_date(auth()->user()->created_at, false) }}</p>
            </div>
        </div>
        <div class="shop-card p-5"><p class="text-sm text-zinc-500">คำสั่งซื้อทั้งหมด</p><p class="mt-1 text-2xl font-semibold">{{ number_format($this->stats['orders']) }}</p></div>
        <div class="shop-card p-5"><p class="text-sm text-zinc-500">ยอดซื้อสะสม</p><p class="mt-1 text-2xl font-semibold text-brown">{{ baht($this->stats['spent']) }}</p></div>
        <div class="shop-card p-5"><p class="text-sm text-zinc-500">รอชำระเงิน</p><p class="mt-1 text-2xl font-semibold">{{ $this->stats['pending'] }}</p></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[380px_1fr]">
        <form wire:submit="saveProfile" class="shop-card h-fit space-y-4 p-5">
            <flux:heading size="lg">ข้อมูลส่วนตัว</flux:heading>
            <flux:input wire:model="name" label="ชื่อ-นามสกุล" />
            <flux:input wire:model="email" label="อีเมล" type="email" />
            <flux:input wire:model="phone" label="เบอร์โทรศัพท์" type="tel" />
            <div class="flex items-center justify-between">
                <flux:button type="submit" variant="primary">บันทึก</flux:button>
                <flux:link :href="route('security.edit')" wire:navigate class="text-sm">เปลี่ยนรหัสผ่าน</flux:link>
            </div>
        </form>

        <div class="shop-card p-5">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">สมุดที่อยู่</flux:heading>
                <flux:button size="sm" icon="plus" wire:click="newAddress">เพิ่มที่อยู่</flux:button>
            </div>
            @forelse ($this->addresses as $address)
                <div class="mb-3 flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 sm:flex-row sm:items-center" wire:key="a-{{ $address->id }}">
                    <div class="flex-1 text-sm">
                        <p class="font-medium text-zinc-900">{{ $address->recipient_name }} · {{ $address->phone }}
                            @if ($address->is_default) <flux:badge size="sm" color="amber" class="ms-1">ที่อยู่หลัก</flux:badge> @endif
                        </p>
                        <p class="text-zinc-600">{{ $address->fullAddress() }}</p>
                    </div>
                    <div class="flex gap-1">
                        @unless ($address->is_default)
                            <flux:button size="xs" variant="ghost" wire:click="makeDefault({{ $address->id }})">ตั้งเป็นหลัก</flux:button>
                        @endunless
                        <flux:button size="xs" icon="pencil-square" wire:click="editAddress({{ $address->id }})">แก้ไข</flux:button>
                        <flux:button size="xs" variant="danger" icon="trash" wire:click="deleteAddress({{ $address->id }})" wire:confirm="ต้องการลบที่อยู่นี้ใช่หรือไม่?" />
                    </div>
                </div>
            @empty
                <x-empty-state icon="map-pin" title="ยังไม่มีที่อยู่" description="เพิ่มที่อยู่เพื่อใช้ในการจัดส่งสินค้า" class="border-dashed shadow-none" />
            @endforelse
        </div>
    </div>

    <flux:modal name="address-form" class="w-full max-w-2xl">
        <form wire:submit="saveAddress" class="space-y-5">
            <flux:heading size="lg">{{ $form->address ? 'แก้ไขที่อยู่' : 'เพิ่มที่อยู่ใหม่' }}</flux:heading>
            <x-address-fields />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">ยกเลิก</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">บันทึก</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
