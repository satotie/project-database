<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::shop')] #[Title('สินค้าทั้งหมด')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $gender = '';

    #[Url(except: '')]
    public string $minPrice = '';

    #[Url(except: '')]
    public string $maxPrice = '';

    #[Url(except: '')]
    public string $size = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function updated($property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'brand', 'category', 'gender', 'minPrice', 'maxPrice', 'size', 'sort']);
        $this->resetPage();
    }

    #[Computed]
    public function products()
    {
        $query = Product::active()
            ->with(['brand', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->when($this->brand !== '', fn ($q) => $q->where('brand_id', $this->brand))
            ->when($this->category !== '', fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $this->category)))
            ->when($this->gender !== '', fn ($q) => $q->where('gender', $this->gender))
            ->when(is_numeric($this->minPrice), fn ($q) => $q->where('price', '>=', (float) $this->minPrice))
            ->when(is_numeric($this->maxPrice), fn ($q) => $q->where('price', '<=', (float) $this->maxPrice))
            // "Size in stock": the product must have that size with stock > 0.
            ->when($this->size !== '', fn ($q) => $q->whereHas('variants', fn ($v) => $v->where('size', $this->size)->where('stock', '>', 0)));

        match ($this->sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count'),
            default => $query->latest()->orderByDesc('id'),
        };

        return $query->paginate(12);
    }

    #[Computed]
    public function brands()
    {
        return Brand::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function categories()
    {
        return Category::orderBy('name')->get(['id', 'name', 'slug']);
    }

    #[Computed]
    public function sizes()
    {
        return ProductVariant::query()->distinct()->orderByRaw('CAST(size AS DECIMAL(5,1))')->pluck('size');
    }

    public function hasFilters(): bool
    {
        return $this->search.$this->brand.$this->category.$this->gender.$this->minPrice.$this->maxPrice.$this->size !== '';
    }
}; ?>

<div class="shop-container py-8">
    <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <flux:heading size="xl" level="1">สินค้าทั้งหมด</flux:heading>
            <flux:text>พบ {{ number_format($this->products->total()) }} รายการ</flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:select wire:model.live="sort" size="sm" class="w-48" aria-label="เรียงตาม">
                <flux:select.option value="newest">ใหม่ล่าสุด</flux:select.option>
                <flux:select.option value="price_asc">ราคา: ต่ำ → สูง</flux:select.option>
                <flux:select.option value="price_desc">ราคา: สูง → ต่ำ</flux:select.option>
                <flux:select.option value="rating">คะแนนรีวิวสูงสุด</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[260px_1fr]">
        {{-- Filters --}}
        <aside class="shop-card h-fit space-y-5 p-5 lg:sticky lg:top-20">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" placeholder="ค้นหาชื่อสินค้า" label="ค้นหา" />

            <flux:select wire:model.live="brand" label="แบรนด์">
                <flux:select.option value="">ทุกแบรนด์</flux:select.option>
                @foreach ($this->brands as $b)
                    <flux:select.option value="{{ $b->id }}">{{ $b->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="category" label="หมวดหมู่">
                <flux:select.option value="">ทุกหมวดหมู่</flux:select.option>
                @foreach ($this->categories as $c)
                    <flux:select.option value="{{ $c->slug }}">{{ $c->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:radio.group wire:model.live="gender" label="เพศ">
                <flux:radio value="" label="ทั้งหมด" />
                @foreach (Product::GENDERS as $value => $label)
                    <flux:radio :value="$value" :label="$label" />
                @endforeach
            </flux:radio.group>

            <div>
                <flux:label>ช่วงราคา (บาท)</flux:label>
                <div class="mt-2 flex items-center gap-2">
                    <flux:input wire:model.live.debounce.500ms="minPrice" type="number" min="0" placeholder="ต่ำสุด" size="sm" />
                    <span class="text-zinc-400">–</span>
                    <flux:input wire:model.live.debounce.500ms="maxPrice" type="number" min="0" placeholder="สูงสุด" size="sm" />
                </div>
            </div>

            <div>
                <flux:label>ไซซ์ที่มีสินค้า (EU)</flux:label>
                <div class="mt-2 grid grid-cols-5 gap-1.5">
                    @foreach ($this->sizes as $s)
                        <button type="button" wire:click="$set('size', '{{ $size === $s ? '' : $s }}')"
                            @class([
                                'rounded-lg border py-1.5 text-sm transition',
                                'border-gold bg-gold text-brown-dark font-medium' => $size === $s,
                                'border-zinc-200 bg-white hover:border-gold' => $size !== $s,
                            ])>{{ $s }}</button>
                    @endforeach
                </div>
            </div>

            @if ($this->hasFilters())
                <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark" class="w-full">ล้างตัวกรอง</flux:button>
            @endif
        </aside>

        {{-- Grid --}}
        <div>
            @if ($this->products->isEmpty())
                <x-empty-state icon="magnifying-glass" title="ไม่พบสินค้าที่ตรงกับเงื่อนไข" description="ลองปรับตัวกรองหรือคำค้นหาใหม่อีกครั้ง">
                    <flux:button wire:click="clearFilters" size="sm">ล้างตัวกรอง</flux:button>
                </x-empty-state>
            @else
                <div class="grid grid-cols-2 gap-4 md:grid-cols-3" wire:loading.class="opacity-60">
                    @foreach ($this->products as $product)
                        <x-product-card :product="$product" wire:key="p-{{ $product->id }}" />
                    @endforeach
                </div>
                <div class="mt-6">
                    <flux:pagination :paginator="$this->products" />
                </div>
            @endif
        </div>
    </div>
</div>
