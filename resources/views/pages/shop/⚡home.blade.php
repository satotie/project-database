<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::shop')] #[Title('หน้าแรก')] class extends Component {
    #[Computed]
    public function newProducts()
    {
        return Product::active()
            ->with(['brand', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->latest()
            ->take(8)
            ->get();
    }

    #[Computed]
    public function brands()
    {
        return Brand::withCount(['products' => fn ($q) => $q->active()])->orderBy('name')->get();
    }

    #[Computed]
    public function categories()
    {
        return Category::withCount(['products' => fn ($q) => $q->active()])->orderBy('name')->get();
    }
}; ?>

@php
    $categoryLabels = ['Running' => 'รองเท้าวิ่ง', 'Sneakers' => 'สนีกเกอร์', 'Basketball' => 'รองเท้าบาสเกตบอล', 'Sandals' => 'รองเท้าแตะ'];
    $categoryIcons = ['Running' => 'bolt', 'Sneakers' => 'sparkles', 'Basketball' => 'trophy', 'Sandals' => 'sun'];
@endphp

<div>
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-sand via-cream to-[#ead8bd]">
        <div class="shop-container grid items-center gap-10 py-14 md:grid-cols-2 md:py-20">
            <div>
                <flux:badge color="amber" size="sm" class="mb-4">คอลเลกชันใหม่ 2026</flux:badge>
                <h1 class="text-4xl font-semibold leading-tight text-brown-dark md:text-5xl">ก้าวที่มั่นใจ<br>เริ่มจากรองเท้าที่ใช่</h1>
                <p class="mt-4 max-w-md text-lg text-zinc-600">รองเท้าแท้จาก Nike, Adidas, New Balance และอีกมากมาย เลือกไซซ์ที่ใช่ เช็กสต็อกได้ทันที ส่งฟรีเมื่อซื้อครบ ฿3,000</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <flux:button variant="primary" :href="route('products.index')" icon-trailing="arrow-right" wire:navigate>ช้อปเลย</flux:button>
                    <flux:button variant="ghost" :href="route('products.index', ['category' => 'running'])" wire:navigate>ดูรองเท้าวิ่ง</flux:button>
                </div>
                <div class="mt-10 flex gap-8 text-sm text-zinc-600">
                    <div><p class="text-2xl font-semibold text-brown">100%</p>ของแท้</div>
                    <div><p class="text-2xl font-semibold text-brown">฿0</p>ส่งฟรีเมื่อครบ ฿3,000</div>
                    <div><p class="text-2xl font-semibold text-brown">{{ $this->brands->count() }}+</p>แบรนด์ดัง</div>
                </div>
            </div>
            <div class="relative">
                <div class="absolute -inset-6 rounded-full bg-gold/20 blur-3xl"></div>
                @if ($hero = $this->newProducts->first())
                    <img src="{{ $hero->main_image_url }}" alt="{{ $hero->name }}" class="relative w-full rounded-3xl border border-white/60 shadow-xl">
                @endif
            </div>
        </div>
    </section>

    {{-- New products --}}
    <section class="shop-container py-14">
        <div class="mb-6 flex items-end justify-between">
            <div>
                <flux:heading size="xl" level="2">สินค้ามาใหม่</flux:heading>
                <flux:text>รุ่นล่าสุดที่เพิ่งเข้าร้าน</flux:text>
            </div>
            <flux:link :href="route('products.index')" wire:navigate>ดูทั้งหมด →</flux:link>
        </div>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->newProducts as $product)
                <x-product-card :product="$product" wire:key="new-{{ $product->id }}" />
            @endforeach
        </div>
    </section>

    {{-- Brands --}}
    <section class="bg-white/60 py-14">
        <div class="shop-container">
            <flux:heading size="xl" level="2" class="mb-6">ช้อปตามแบรนด์</flux:heading>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($this->brands as $brand)
                    <a href="{{ route('products.index', ['brand' => $brand->id]) }}" wire:navigate class="shop-card flex flex-col items-center justify-center gap-1 px-4 py-6 text-center transition hover:border-gold hover:shadow-md">
                        @if ($brand->logo_path)
                            <img src="/storage/{{ $brand->logo_path }}" alt="{{ $brand->name }}" class="h-10 object-contain">
                        @else
                            <span class="text-lg font-semibold tracking-tight text-brown-dark">{{ $brand->name }}</span>
                        @endif
                        <span class="text-xs text-zinc-500">{{ $brand->products_count }} รุ่น</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Categories --}}
    <section class="shop-container py-14">
        <flux:heading size="xl" level="2" class="mb-6">ช้อปตามหมวดหมู่</flux:heading>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($this->categories as $category)
                <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate class="group shop-card flex items-center gap-4 p-5 transition hover:border-gold hover:shadow-md">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-sand text-gold-dark group-hover:bg-gold group-hover:text-brown-dark">
                        <flux:icon :name="$categoryIcons[$category->name] ?? 'tag'" class="size-6" />
                    </span>
                    <div>
                        <p class="font-medium text-zinc-900">{{ $categoryLabels[$category->name] ?? $category->name }}</p>
                        <p class="text-xs text-zinc-500">{{ $category->name }} · {{ $category->products_count }} รุ่น</p>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Call to action --}}
    <section class="shop-container">
        <div class="flex flex-col items-start justify-between gap-6 rounded-3xl bg-brown-dark px-8 py-10 text-white md:flex-row md:items-center">
            <div>
                <h2 class="text-2xl font-semibold">สมัครสมาชิกวันนี้ รับส่วนลด 10%</h2>
                <p class="mt-2 text-zinc-300">ใช้โค้ด <span class="rounded bg-gold px-2 py-0.5 font-mono font-semibold text-brown-dark">WELCOME10</span> ตอนชำระเงิน ใช้ได้กับสินค้าทุกชิ้น</p>
            </div>
            @guest
                <flux:button variant="primary" :href="route('register')" wire:navigate>สมัครสมาชิกฟรี</flux:button>
            @else
                <flux:button variant="primary" :href="route('products.index')" wire:navigate>เลือกซื้อสินค้า</flux:button>
            @endguest
        </div>
    </section>
</div>
