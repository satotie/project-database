<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('คำสั่งซื้อ')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->with(['user:id,name,email', 'latestPayment'])
            ->withCount('items')
            ->when($this->status === 'to_ship', fn ($q) => $q->whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing]))
            ->when($this->status === 'slip_review', fn ($q) => $q->whereHas('payments', fn ($p) => $p->where('status', PaymentStatus::Pending)))
            ->when(OrderStatus::tryFrom($this->status), fn ($q, $s) => $q->where('status', $s))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($w) => $w->where('code', 'like', $term)
                    ->orWhere('ship_name', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function counts(): array
    {
        return Order::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();
    }
}; ?>

<div class="space-y-6">
    <flux:heading size="xl" level="1">คำสั่งซื้อ</flux:heading>

    <div class="flex gap-2 overflow-x-auto pb-1">
        @php
            $tabs = ['' => 'ทั้งหมด', 'slip_review' => 'สลิปรอตรวจ', 'to_ship' => 'รอจัดส่ง']
                + collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label().' ('.($this->counts[$s->value] ?? 0).')'])->all();
        @endphp
        @foreach ($tabs as $value => $label)
            <button type="button" wire:click="$set('status', '{{ $value }}')" @class([
                'shrink-0 rounded-full border px-4 py-1.5 text-sm transition',
                'border-gold bg-gold font-medium text-brown-dark' => $status === $value,
                'border-zinc-200 bg-white hover:border-gold' => $status !== $value,
            ])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="shop-card p-4">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="ค้นหารหัสคำสั่งซื้อ ชื่อหรืออีเมลลูกค้า" />
    </div>

    <div class="shop-card overflow-x-auto p-2">
        <flux:table :paginate="$this->orders">
            <flux:table.columns>
                <flux:table.column>รหัส</flux:table.column>
                <flux:table.column>ลูกค้า</flux:table.column>
                <flux:table.column>วันที่สั่ง</flux:table.column>
                <flux:table.column>จัดส่ง</flux:table.column>
                <flux:table.column>สถานะ</flux:table.column>
                <flux:table.column>การชำระเงิน</flux:table.column>
                <flux:table.column align="end">ยอดรวม</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->orders as $order)
                    <flux:table.row :key="$order->id">
                        <flux:table.cell><a href="{{ route('staff.orders.show', $order) }}" wire:navigate class="font-mono font-medium text-accent-content hover:underline">{{ $order->code }}</a></flux:table.cell>
                        <flux:table.cell>
                            <p>{{ $order->user->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $order->user->email }}</p>
                        </flux:table.cell>
                        <flux:table.cell>{{ thai_date($order->created_at) }}</flux:table.cell>
                        <flux:table.cell>{{ $order->shipping_method->name }}</flux:table.cell>
                        <flux:table.cell><x-status-badge :status="$order->status" /></flux:table.cell>
                        <flux:table.cell>
                            @if ($order->latestPayment)
                                <flux:badge size="sm" :color="$order->latestPayment->status->color()">{{ $order->latestPayment->status->label() }}</flux:badge>
                            @else
                                <span class="text-xs text-zinc-400">ยังไม่แนบสลิป</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="font-medium">{{ baht($order->total) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">ไม่พบคำสั่งซื้อ</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
