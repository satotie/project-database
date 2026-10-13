<?php

use App\Enums\OrderStatus;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('คำสั่งซื้อของฉัน')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        return auth()->user()->orders()
            ->with(['items', 'latestPayment'])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(10);
    }
}; ?>

<div>
    <flux:heading size="xl" level="1" class="mb-4">คำสั่งซื้อของฉัน</flux:heading>

    <div class="mb-6 flex gap-2 overflow-x-auto pb-1">
        @foreach (['' => 'ทั้งหมด'] + collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all() as $value => $label)
            <button type="button" wire:click="$set('status', '{{ $value }}')" @class([
                'shrink-0 rounded-full border px-4 py-1.5 text-sm transition',
                'border-gold bg-gold font-medium text-brown-dark' => $status === $value,
                'border-zinc-200 bg-white hover:border-gold' => $status !== $value,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($this->orders->isEmpty())
        <x-empty-state icon="clipboard-document-list" title="ยังไม่มีคำสั่งซื้อ" description="{{ $status ? 'ไม่มีคำสั่งซื้อในสถานะนี้' : 'เริ่มช้อปรองเท้าคู่แรกของคุณได้เลย' }}">
            <flux:button variant="primary" :href="route('products.index')" wire:navigate>เลือกซื้อสินค้า</flux:button>
        </x-empty-state>
    @else
        <div class="space-y-3">
            @foreach ($this->orders as $order)
                <a href="{{ route('orders.show', $order) }}" wire:navigate wire:key="o-{{ $order->id }}" class="shop-card flex flex-col gap-3 p-5 transition hover:border-gold sm:flex-row sm:items-center">
                    <div class="flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono font-semibold text-zinc-900">{{ $order->code }}</span>
                            <x-status-badge :status="$order->status" />
                            @if ($order->status === OrderStatus::PendingPayment && $order->latestPayment?->status === \App\Enums\PaymentStatus::Rejected)
                                <flux:badge size="sm" color="red">สลิปไม่ผ่าน กรุณาแนบใหม่</flux:badge>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-zinc-500">{{ thai_date($order->created_at) }} · {{ $order->items->sum('quantity') }} คู่ · {{ $order->items->pluck('product_name')->unique()->join(', ') }}</p>
                    </div>
                    <div class="flex items-center justify-between gap-4 sm:justify-end">
                        <span class="text-lg font-semibold text-brown">{{ baht($order->total) }}</span>
                        <flux:icon.chevron-right class="size-5 text-zinc-400" />
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6"><flux:pagination :paginator="$this->orders" /></div>
    @endif
</div>
