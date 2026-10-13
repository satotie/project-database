<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('แดชบอร์ด')] class extends Component {
    #[Computed]
    public function stats(): array
    {
        return [
            'today_revenue' => (float) Order::whereIn('status', OrderStatus::revenueValues())->whereDate('paid_at', today())->sum('total'),
            'today_orders' => Order::whereDate('created_at', today())->count(),
            'pending_payment' => Order::where('status', OrderStatus::PendingPayment)->count(),
            'slips_to_review' => Payment::where('status', PaymentStatus::Pending)->count(),
            'to_ship' => Order::whereIn('status', [OrderStatus::Paid, OrderStatus::Preparing])->count(),
            'low_stock' => ProductVariant::where('stock', '<=', ProductVariant::LOW_STOCK)->count(),
            'customers' => User::where('role', 'customer')->count(),
        ];
    }

    #[Computed]
    public function latestOrders()
    {
        return Order::with('user:id,name')->latest()->take(10)->get();
    }

    #[Computed]
    public function lowStockVariants()
    {
        return ProductVariant::with('product:id,name')
            ->where('stock', '<=', ProductVariant::LOW_STOCK)
            ->orderBy('stock')
            ->take(8)
            ->get();
    }
}; ?>

<div class="space-y-6">
    <div>
        <flux:heading size="xl" level="1">แดชบอร์ด</flux:heading>
        <flux:text>ภาพรวมร้านค้าวันที่ {{ thai_date(now(), false) }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="รายได้วันนี้" :value="baht($this->stats['today_revenue'])" icon="banknotes" :hint="'คำสั่งซื้อใหม่วันนี้ '.$this->stats['today_orders'].' รายการ'" />
        <x-stat-card label="รอชำระเงิน" :value="$this->stats['pending_payment']" icon="clock" :hint="'สลิปรอตรวจ '.$this->stats['slips_to_review'].' รายการ'" :href="route('staff.orders.index', ['status' => 'PENDING_PAYMENT'])" />
        <x-stat-card label="รอจัดส่ง" :value="$this->stats['to_ship']" icon="truck" hint="ชำระแล้ว + กำลังเตรียม" :href="route('staff.orders.index', ['status' => 'to_ship'])" />
        <x-stat-card label="สินค้าใกล้หมด (≤ 3)" :value="$this->stats['low_stock']" icon="exclamation-triangle" hint="จำนวนไซซ์" :href="route('staff.products.index', ['lowStock' => 1])" />
        <x-stat-card label="ลูกค้าทั้งหมด" :value="number_format($this->stats['customers'])" icon="users" :href="route('staff.users')" />
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div class="shop-card p-5">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">คำสั่งซื้อล่าสุด</flux:heading>
                <flux:link :href="route('staff.orders.index')" wire:navigate class="text-sm">ดูทั้งหมด →</flux:link>
            </div>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>รหัส</flux:table.column>
                    <flux:table.column>ลูกค้า</flux:table.column>
                    <flux:table.column>วันที่</flux:table.column>
                    <flux:table.column>สถานะ</flux:table.column>
                    <flux:table.column align="end">ยอดรวม</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->latestOrders as $order)
                        <flux:table.row :key="$order->id">
                            <flux:table.cell><a href="{{ route('staff.orders.show', $order) }}" wire:navigate class="font-mono font-medium text-accent-content hover:underline">{{ $order->code }}</a></flux:table.cell>
                            <flux:table.cell>{{ $order->user->name }}</flux:table.cell>
                            <flux:table.cell>{{ thai_date($order->created_at) }}</flux:table.cell>
                            <flux:table.cell><x-status-badge :status="$order->status" /></flux:table.cell>
                            <flux:table.cell align="end" class="font-medium">{{ baht($order->total) }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="shop-card h-fit p-5">
            <flux:heading size="lg" class="mb-3">สต็อกใกล้หมด</flux:heading>
            @forelse ($this->lowStockVariants as $variant)
                <div class="flex items-center justify-between border-b border-zinc-100 py-2 text-sm last:border-0">
                    <a href="{{ route('staff.products.edit', $variant->product_id) }}" wire:navigate class="min-w-0 truncate hover:underline">{{ $variant->product->name }} <span class="text-zinc-500">· {{ $variant->size }}</span></a>
                    <flux:badge size="sm" :color="$variant->stock === 0 ? 'red' : 'amber'">{{ $variant->stock === 0 ? 'หมด' : 'เหลือ '.$variant->stock }}</flux:badge>
                </div>
            @empty
                <flux:text>สต็อกเพียงพอทุกไซซ์</flux:text>
            @endforelse
        </div>
    </div>
</div>
