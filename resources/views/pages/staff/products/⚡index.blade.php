<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('จัดการสินค้า')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: false)]
    public bool $lowStock = false;

    public function updated($property): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function products()
    {
        return Product::with(['brand', 'category', 'images', 'variants'])
            ->withSum('variants', 'stock')
            ->withCount('reviews')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$this->search}%"))))
            ->when($this->brand !== '', fn ($q) => $q->where('brand_id', $this->brand))
            ->when($this->category !== '', fn ($q) => $q->where('category_id', $this->category))
            ->when($this->lowStock, fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('stock', '<=', ProductVariant::LOW_STOCK)))
            ->latest('id')
            ->paginate(15);
    }

    #[Computed]
    public function brands()
    {
        return Brand::orderBy('name')->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get();
    }

    public function toggleActive(Product $product): void
    {
        $product->update(['is_active' => ! $product->is_active]);
        Flux::toast(text: $product->is_active ? 'เปิดขายสินค้าแล้ว' : 'ปิดการขายสินค้าแล้ว');
    }

    /** Products that were ever ordered are only disabled, so order history keeps its FK. */
    public function delete(Product $product): void
    {
        $hasOrders = $product->variants()->whereHas('orderItems')->exists();

        if ($hasOrders) {
            $product->update(['is_active' => false]);
            Flux::toast(variant: 'warning', text: 'สินค้านี้มีประวัติการสั่งซื้อ จึงปิดการขายแทนการลบ');

            return;
        }

        $paths = $product->images->pluck('path')->all();
        $product->delete();
        Storage::disk('public')->delete($paths);
        Flux::toast(variant: 'success', text: 'ลบสินค้าเรียบร้อย');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">สินค้า</flux:heading>
            <flux:text>ทั้งหมด {{ $this->products->total() }} รายการ</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('staff.products.create')" wire:navigate>เพิ่มสินค้า</flux:button>
    </div>

    <div class="shop-card grid gap-3 p-4 md:grid-cols-[1fr_180px_180px_auto]">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="ค้นหาชื่อสินค้าหรือ SKU" />
        <flux:select wire:model.live="brand">
            <flux:select.option value="">ทุกแบรนด์</flux:select.option>
            @foreach ($this->brands as $b)
                <flux:select.option value="{{ $b->id }}">{{ $b->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="category">
            <flux:select.option value="">ทุกหมวดหมู่</flux:select.option>
            @foreach ($this->categories as $c)
                <flux:select.option value="{{ $c->id }}">{{ $c->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <div class="flex items-center"><flux:checkbox wire:model.live="lowStock" label="เฉพาะสต็อกใกล้หมด" /></div>
    </div>

    <div class="shop-card overflow-x-auto p-2">
        <flux:table :paginate="$this->products">
            <flux:table.columns>
                <flux:table.column>สินค้า</flux:table.column>
                <flux:table.column>หมวดหมู่</flux:table.column>
                <flux:table.column align="end">ราคา</flux:table.column>
                <flux:table.column>ไซซ์ / สต็อก</flux:table.column>
                <flux:table.column>สถานะ</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->products as $product)
                    <flux:table.row :key="$product->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <img src="{{ $product->main_image_url }}" alt="" class="size-12 rounded-lg bg-sand object-cover">
                                <div>
                                    <a href="{{ route('staff.products.edit', $product) }}" wire:navigate class="font-medium text-zinc-900 hover:underline">{{ $product->name }}</a>
                                    <p class="text-xs text-zinc-500">{{ $product->brand->name }} · {{ $product->genderLabel() }}</p>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $product->category->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-medium">{{ baht($product->price) }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex max-w-xs flex-wrap gap-1 whitespace-normal">
                                @foreach ($product->variants as $v)
                                    <span @class([
                                        'rounded px-1.5 py-0.5 text-xs',
                                        'bg-red-100 text-red-700' => $v->stock === 0,
                                        'bg-amber-100 text-amber-800' => $v->stock > 0 && $v->stock <= 3,
                                        'bg-zinc-100 text-zinc-700' => $v->stock > 3,
                                    ])>{{ $v->size }}:{{ $v->stock }}</span>
                                @endforeach
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">รวม {{ $product->totalStock() }} คู่</p>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:switch :checked="$product->is_active" wire:click="toggleActive({{ $product->id }})" aria-label="เปิดขาย" />
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('staff.products.edit', $product)" wire:navigate>แก้ไข</flux:menu.item>
                                    <flux:menu.item icon="eye" :href="route('products.show', $product)" target="_blank">ดูหน้าร้าน</flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $product->id }})" wire:confirm="ยืนยันการลบสินค้านี้? (ถ้ามีประวัติการสั่งซื้อ ระบบจะปิดการขายแทน)">ลบ</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-10 text-center text-zinc-500">ไม่พบสินค้า</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
