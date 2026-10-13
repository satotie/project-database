<?php

use App\Enums\OrderStatus;
use App\Models\Coupon;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('คูปอง')] class extends Component {
    public ?int $couponId = null;

    public string $code = '';

    public string $description = '';

    public string $discount_percent = '10';

    public bool $applies_to_all = false;

    public string $min_order_amount = '0';

    public string $usage_limit = '';

    public string $starts_at = '';

    public string $expires_at = '';

    public bool $is_active = true;

    public array $productIds = [];

    public string $productSearch = '';

    #[Computed]
    public function coupons()
    {
        return Coupon::withCount([
            'products',
            'orders as used_count' => fn ($q) => $q->where('status', '!=', OrderStatus::Cancelled->value),
        ])->latest('id')->get();
    }

    #[Computed]
    public function products()
    {
        return Product::with('brand:id,name', 'category:id,name')
            ->when($this->productSearch !== '', fn ($q) => $q->where('name', 'like', "%{$this->productSearch}%"))
            ->orderBy('name')
            ->get(['id', 'name', 'brand_id', 'category_id', 'price']);
    }

    public function create(): void
    {
        $this->resetForm();
        Flux::modal('coupon-form')->show();
    }

    public function edit(Coupon $coupon): void
    {
        $this->resetForm();
        $this->couponId = $coupon->id;
        $this->code = $coupon->code;
        $this->description = (string) $coupon->description;
        $this->discount_percent = (string) $coupon->discount_percent;
        $this->applies_to_all = $coupon->applies_to_all;
        $this->min_order_amount = (string) (float) $coupon->min_order_amount;
        $this->usage_limit = (string) ($coupon->usage_limit ?? '');
        $this->starts_at = $coupon->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->expires_at = $coupon->expires_at?->format('Y-m-d\TH:i') ?? '';
        $this->is_active = $coupon->is_active;
        $this->productIds = $coupon->products()->pluck('products.id')->map(fn ($id) => (string) $id)->all();
        Flux::modal('coupon-form')->show();
    }

    private function resetForm(): void
    {
        $this->reset(['couponId', 'code', 'description', 'discount_percent', 'applies_to_all', 'min_order_amount', 'usage_limit', 'starts_at', 'expires_at', 'is_active', 'productIds', 'productSearch']);
        $this->resetErrorBag();
    }

    public function selectCategory(string $category): void
    {
        $ids = Product::whereHas('category', fn ($q) => $q->where('name', $category))->pluck('id')->map(fn ($id) => (string) $id);
        $this->productIds = collect($this->productIds)->merge($ids)->unique()->values()->all();
    }

    public function save(): void
    {
        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('coupons', 'code')->ignore($this->couponId)],
            'description' => ['nullable', 'string', 'max:255'],
            'discount_percent' => ['required', 'integer', 'between:1,100'],
            'applies_to_all' => ['boolean'],
            'min_order_amount' => ['required', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => array_filter(['nullable', 'date', filled($this->starts_at) ? 'after:starts_at' : null]),
            'is_active' => ['boolean'],
            'productIds' => [Rule::requiredIf(! $this->applies_to_all), 'array'],
            'productIds.*' => ['exists:products,id'],
        ], [
            'productIds.required' => 'กรุณาเลือกสินค้าอย่างน้อย 1 รายการ หรือเลือก "ใช้ได้กับสินค้าทุกชิ้น"',
            'discount_percent.between' => 'ส่วนลดต้องอยู่ระหว่าง 1–100%',
            'expires_at.after' => 'วันหมดอายุต้องหลังวันเริ่มใช้',
        ], [
            'code' => 'รหัสคูปอง', 'discount_percent' => 'ส่วนลด', 'min_order_amount' => 'ยอดขั้นต่ำ',
            'usage_limit' => 'จำนวนสิทธิ์', 'starts_at' => 'วันเริ่มใช้', 'expires_at' => 'วันหมดอายุ',
        ]);

        $coupon = Coupon::updateOrCreate(['id' => $this->couponId], [
            'code' => $data['code'],
            'description' => $data['description'] ?: null,
            'discount_percent' => (int) $data['discount_percent'],
            'applies_to_all' => $data['applies_to_all'],
            'min_order_amount' => $data['min_order_amount'],
            'usage_limit' => $data['usage_limit'] !== '' ? (int) $data['usage_limit'] : null,
            'starts_at' => $data['starts_at'] ?: null,
            'expires_at' => $data['expires_at'] ?: null,
            'is_active' => $data['is_active'],
        ]);

        // coupon_product (M:N) — cleared when the coupon applies to everything.
        $coupon->products()->sync($this->applies_to_all ? [] : $this->productIds);

        unset($this->coupons);
        Flux::modal('coupon-form')->close();
        Flux::toast(variant: 'success', text: "บันทึกคูปอง {$coupon->code} แล้ว");
    }

    public function toggle(Coupon $coupon): void
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);
        unset($this->coupons);
    }

    public function delete(Coupon $coupon): void
    {
        // orders.coupon_id is nullOnDelete, so past orders keep their stored discount.
        $coupon->delete();
        unset($this->coupons);
        Flux::toast(text: 'ลบคูปองแล้ว');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">คูปองส่วนลด</flux:heading>
            <flux:text>ส่วนลดเป็นเปอร์เซ็นต์ ใช้ได้กับสินค้าที่เลือกหรือทุกชิ้น (1 คูปองต่อคำสั่งซื้อ)</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">สร้างคูปอง</flux:button>
    </div>

    <div class="shop-card overflow-x-auto p-2">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>รหัส</flux:table.column>
                <flux:table.column align="end">ส่วนลด</flux:table.column>
                <flux:table.column>ใช้กับ</flux:table.column>
                <flux:table.column align="end">ขั้นต่ำ</flux:table.column>
                <flux:table.column>ระยะเวลา</flux:table.column>
                <flux:table.column align="end">ใช้ไปแล้ว</flux:table.column>
                <flux:table.column>สถานะ</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->coupons as $coupon)
                    @php $expired = $coupon->expires_at && $coupon->expires_at->isPast(); @endphp
                    <flux:table.row :key="$coupon->id">
                        <flux:table.cell>
                            <p class="font-mono font-semibold text-zinc-900">{{ $coupon->code }}</p>
                            <p class="max-w-56 truncate text-xs text-zinc-500">{{ $coupon->description }}</p>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="font-semibold text-brown">{{ $coupon->discount_percent }}%</flux:table.cell>
                        <flux:table.cell>{{ $coupon->applies_to_all ? 'สินค้าทุกชิ้น' : $coupon->products_count.' สินค้า' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ baht($coupon->min_order_amount) }}</flux:table.cell>
                        <flux:table.cell class="text-xs">
                            {{ $coupon->starts_at ? thai_date($coupon->starts_at, false) : 'ไม่จำกัด' }} – {{ $coupon->expires_at ? thai_date($coupon->expires_at, false) : 'ไม่จำกัด' }}
                        </flux:table.cell>
                        <flux:table.cell align="end">{{ $coupon->used_count }} / {{ $coupon->usage_limit ?? '∞' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($expired)
                                <flux:badge size="sm" color="zinc">หมดอายุ</flux:badge>
                            @else
                                <flux:switch :checked="$coupon->is_active" wire:click="toggle({{ $coupon->id }})" aria-label="เปิดใช้งาน" />
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $coupon->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $coupon->id }})" wire:confirm="ลบคูปอง {{ $coupon->code }}?" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-10 text-center text-zinc-500">ยังไม่มีคูปอง</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="coupon-form" class="w-full max-w-3xl">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $couponId ? 'แก้ไขคูปอง' : 'สร้างคูปองใหม่' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="code" label="รหัสคูปอง" class="uppercase" placeholder="SALE15" />
                <flux:input wire:model="discount_percent" type="number" min="1" max="100" label="ส่วนลด (%)" />
                <flux:input wire:model="min_order_amount" type="number" min="0" label="ยอดสั่งซื้อขั้นต่ำ (บาท)" />
                <div class="sm:col-span-3"><flux:input wire:model="description" label="คำอธิบาย" /></div>
                <flux:input wire:model="starts_at" type="datetime-local" label="เริ่มใช้ (ว่าง = ทันที)" />
                <flux:input wire:model="expires_at" type="datetime-local" label="หมดอายุ (ว่าง = ไม่จำกัด)" />
                <flux:input wire:model="usage_limit" type="number" min="1" label="จำนวนสิทธิ์ (ว่าง = ไม่จำกัด)" />
            </div>
            <div class="flex flex-wrap gap-6">
                <flux:switch wire:model.live="applies_to_all" label="ใช้ได้กับสินค้าทุกชิ้น" align="left" />
                <flux:switch wire:model="is_active" label="เปิดใช้งาน" align="left" />
            </div>

            @unless ($applies_to_all)
                <div class="rounded-xl border border-zinc-200 p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <flux:heading>เลือกสินค้าที่ร่วมรายการ ({{ count($productIds) }})</flux:heading>
                        <div class="flex gap-1">
                            @foreach (['Running', 'Sneakers', 'Basketball', 'Sandals'] as $cat)
                                <flux:button size="xs" variant="ghost" wire:click="selectCategory('{{ $cat }}')">+ {{ $cat }}</flux:button>
                            @endforeach
                            <flux:button size="xs" variant="ghost" wire:click="$set('productIds', [])">ล้าง</flux:button>
                        </div>
                    </div>
                    <flux:input wire:model.live.debounce.300ms="productSearch" size="sm" icon="magnifying-glass" placeholder="ค้นหาสินค้า" class="mb-3" />
                    <div class="grid max-h-64 gap-1 overflow-y-auto sm:grid-cols-2">
                        @foreach ($this->products as $product)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-sand" wire:key="cp-{{ $product->id }}">
                                <input type="checkbox" wire:model="productIds" value="{{ $product->id }}" class="accent-[#c9a063]">
                                <span class="truncate">{{ $product->name }}</span>
                                <span class="ms-auto shrink-0 text-xs text-zinc-500">{{ $product->category->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <flux:error name="productIds" class="mt-2" />
                </div>
            @endunless

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">ยกเลิก</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">บันทึกคูปอง</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
