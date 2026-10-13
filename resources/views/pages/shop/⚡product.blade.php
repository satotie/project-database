<?php

use App\Models\Product;
use App\Models\Review;
use App\Services\CartService;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::shop')] class extends Component {
    public Product $product;

    public ?int $variantId = null;

    public int $quantity = 1;

    public int $activeImage = 0;

    // Review form
    public int $rating = 5;

    public string $comment = '';

    public function mount(Product $product): void
    {
        abort_unless($product->is_active || auth()->user()?->isStaff(), 404);

        $this->product = $product->load(['brand', 'category', 'images', 'variants']);

        if ($review = $this->myReview) {
            $this->rating = $review->rating;
            $this->comment = (string) $review->comment;
        }
    }

    public function rendering($view): void
    {
        $view->title($this->product->name);
    }

    #[Computed]
    public function selectedVariant()
    {
        return $this->product->variants->firstWhere('id', $this->variantId);
    }

    #[Computed]
    public function reviews()
    {
        return $this->product->reviews()->with('user:id,name')->latest()->get();
    }

    #[Computed]
    public function ratingSummary(): array
    {
        $reviews = $this->reviews;

        return [
            'average' => round((float) $reviews->avg('rating'), 1),
            'count' => $reviews->count(),
            'breakdown' => collect(range(5, 1))->mapWithKeys(fn ($star) => [$star => $reviews->where('rating', $star)->count()]),
        ];
    }

    #[Computed]
    public function myReview(): ?Review
    {
        return auth()->check()
            ? Review::where('user_id', auth()->id())->where('product_id', $this->product->id)->first()
            : null;
    }

    #[Computed]
    public function canReview(): bool
    {
        return (bool) auth()->user()?->canReview($this->product);
    }

    public function selectVariant(int $id): void
    {
        $variant = $this->product->variants->firstWhere('id', $id);

        if ($variant && $variant->stock > 0) {
            $this->variantId = $id;
            $this->quantity = min(max(1, $this->quantity), $variant->stock);
            $this->resetErrorBag();
        }
    }

    public function addToCart(CartService $cart)
    {
        if (! auth()->check()) {
            // Guests are sent to login and returned here afterwards.
            session()->put('url.intended', route('products.show', $this->product));

            return $this->redirect(route('login'), navigate: true);
        }

        if (! auth()->user()->isCustomer()) {
            Flux::toast(variant: 'warning', text: 'บัญชีพนักงานไม่สามารถสั่งซื้อสินค้าได้');

            return null;
        }

        $this->validate([
            'variantId' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ], [
            'variantId.required' => 'กรุณาเลือกไซซ์',
            'quantity.min' => 'จำนวนต้องอย่างน้อย 1 คู่',
        ]);

        $variant = $this->product->variants()->whereKey($this->variantId)->firstOrFail();
        $cart->add(auth()->user(), $variant, $this->quantity);

        $this->dispatch('cart-updated');
        Flux::toast(variant: 'success', heading: 'เพิ่มลงตะกร้าแล้ว', text: "{$this->product->name} ไซซ์ {$variant->size} × {$this->quantity}");
        $this->quantity = 1;

        return null;
    }

    public function saveReview(): void
    {
        abort_unless($this->canReview, 403);

        $this->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.between' => 'กรุณาให้คะแนน 1–5 ดาว',
            'comment.max' => 'ความคิดเห็นต้องไม่เกิน 1,000 ตัวอักษร',
        ]);

        Review::updateOrCreate(
            ['user_id' => auth()->id(), 'product_id' => $this->product->id],
            ['rating' => $this->rating, 'comment' => trim($this->comment) ?: null],
        );

        unset($this->reviews, $this->ratingSummary, $this->myReview);
        Flux::toast(variant: 'success', text: 'บันทึกรีวิวเรียบร้อย ขอบคุณสำหรับความคิดเห็น');
    }
}; ?>

<div class="shop-container py-8">
    <flux:breadcrumbs class="mb-6">
        <flux:breadcrumbs.item :href="route('home')" wire:navigate>หน้าแรก</flux:breadcrumbs.item>
        <flux:breadcrumbs.item :href="route('products.index')" wire:navigate>สินค้า</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $product->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="grid gap-10 lg:grid-cols-2">
        {{-- Images --}}
        <div>
            @php $images = $product->images; @endphp
            <div class="shop-card overflow-hidden">
                <img src="{{ $images[$activeImage]->url ?? $product->main_image_url }}" alt="{{ $product->name }}" class="aspect-[4/3] w-full object-cover">
            </div>
            @if ($images->count() > 1)
                <div class="mt-3 flex gap-3">
                    @foreach ($images as $i => $image)
                        <button type="button" wire:click="$set('activeImage', {{ $i }})" wire:key="thumb-{{ $image->id }}"
                            @class(['size-20 overflow-hidden rounded-xl border-2 bg-white', 'border-gold' => $activeImage === $i, 'border-transparent' => $activeImage !== $i])>
                            <img src="{{ $image->url }}" alt="" class="size-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Info --}}
        <div>
            <p class="text-sm font-medium uppercase tracking-wide text-accent-content">{{ $product->brand->name }} · {{ $product->category->name }}</p>
            <h1 class="mt-1 text-3xl font-semibold text-brown-dark">{{ $product->name }}</h1>
            <div class="mt-2 flex items-center gap-2 text-sm text-zinc-600">
                <x-stars :rating="$this->ratingSummary['average']" />
                <span>{{ number_format($this->ratingSummary['average'], 1) }}</span>
                <span class="text-zinc-400">·</span>
                <a href="#reviews" class="underline-offset-2 hover:underline">{{ $this->ratingSummary['count'] }} รีวิว</a>
                <span class="text-zinc-400">·</span>
                <span>{{ $product->genderLabel() }}</span>
            </div>
            <p class="mt-5 text-3xl font-semibold text-brown">{{ baht($product->price) }}</p>

            @unless ($product->is_active)
                <flux:callout variant="warning" icon="eye-slash" class="mt-4" heading="สินค้านี้ถูกปิดการขาย (เห็นเฉพาะพนักงาน)" />
            @endunless

            <div class="mt-6">
                <div class="mb-2 flex items-center justify-between">
                    <flux:label>เลือกไซซ์ (EU)</flux:label>
                    @if ($this->selectedVariant)
                        <span class="text-sm text-zinc-500">คงเหลือ {{ $this->selectedVariant->stock }} คู่</span>
                    @endif
                </div>
                <div class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                    @foreach ($product->variants as $variant)
                        <button type="button" wire:key="v-{{ $variant->id }}" wire:click="selectVariant({{ $variant->id }})" @disabled($variant->stock === 0)
                            data-test="size-{{ $variant->size }}"
                            @class([
                                'flex flex-col items-center rounded-xl border px-2 py-2 text-sm transition',
                                'border-gold bg-gold text-brown-dark' => $variantId === $variant->id,
                                'border-zinc-200 bg-white hover:border-gold' => $variantId !== $variant->id && $variant->stock > 0,
                                'cursor-not-allowed border-dashed border-zinc-200 bg-zinc-100 text-zinc-400 line-through' => $variant->stock === 0,
                            ])>
                            <span class="font-semibold">{{ $variant->size }}</span>
                            <span class="text-[11px] no-underline">{{ $variant->stock > 0 ? 'เหลือ '.$variant->stock : 'หมด' }}</span>
                        </button>
                    @endforeach
                </div>
                <flux:error name="variantId" class="mt-2" />
            </div>

            <div class="mt-6 flex items-end gap-3">
                <div class="w-28">
                    <flux:input wire:model="quantity" type="number" min="1" :max="$this->selectedVariant?->stock" label="จำนวน" />
                </div>
                <flux:button wire:click="addToCart" variant="primary" icon="shopping-cart" class="flex-1" data-test="add-to-cart" :disabled="$product->variants->sum('stock') === 0">
                    {{ $product->variants->sum('stock') === 0 ? 'สินค้าหมด' : 'เพิ่มลงตะกร้า' }}
                </flux:button>
            </div>
            <flux:error name="quantity" class="mt-2" />

            <div class="mt-8 border-t border-zinc-200 pt-6">
                <flux:heading size="lg">รายละเอียดสินค้า</flux:heading>
                <p class="mt-2 whitespace-pre-line leading-relaxed text-zinc-700">{{ $product->description }}</p>
            </div>

            <ul class="mt-6 grid gap-2 text-sm text-zinc-600 sm:grid-cols-2">
                <li class="flex items-center gap-2"><flux:icon.truck class="size-5 text-gold-dark" /> ส่งฟรีเมื่อซื้อครบ ฿3,000</li>
                <li class="flex items-center gap-2"><flux:icon.shield-check class="size-5 text-gold-dark" /> สินค้าแท้ 100%</li>
            </ul>
        </div>
    </div>

    {{-- Reviews --}}
    <section id="reviews" class="mt-14 grid gap-8 lg:grid-cols-[300px_1fr]">
        <div class="shop-card h-fit p-6">
            <flux:heading size="lg">คะแนนรีวิว</flux:heading>
            <div class="mt-3 flex items-end gap-2">
                <span class="text-5xl font-semibold text-brown-dark">{{ number_format($this->ratingSummary['average'], 1) }}</span>
                <span class="mb-1 text-zinc-500">/ 5</span>
            </div>
            <x-stars :rating="$this->ratingSummary['average']" size="size-5" class="mt-1" />
            <p class="mt-1 text-sm text-zinc-500">จาก {{ $this->ratingSummary['count'] }} รีวิว</p>
            <div class="mt-4 space-y-1.5">
                @foreach ($this->ratingSummary['breakdown'] as $star => $count)
                    @php $pct = $this->ratingSummary['count'] ? $count / $this->ratingSummary['count'] * 100 : 0; @endphp
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-6 text-zinc-600">{{ $star }}★</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-100"><div class="h-full rounded-full bg-gold" style="width: {{ $pct }}%"></div></div>
                        <span class="w-6 text-end text-zinc-500">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-4">
            @if ($this->canReview)
                <form wire:submit="saveReview" class="shop-card space-y-4 p-6" data-test="review-form">
                    <flux:heading size="lg">{{ $this->myReview ? 'แก้ไขรีวิวของคุณ' : 'เขียนรีวิวสินค้านี้' }}</flux:heading>
                    <div>
                        <flux:label>ให้คะแนน</flux:label>
                        <div class="mt-1 flex gap-1">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button" wire:click="$set('rating', {{ $i }})" aria-label="{{ $i }} ดาว">
                                    <flux:icon.star variant="solid" @class(['size-8 transition', 'text-gold' => $rating >= $i, 'text-zinc-300' => $rating < $i]) />
                                </button>
                            @endfor
                        </div>
                        <flux:error name="rating" />
                    </div>
                    <flux:textarea wire:model="comment" label="ความคิดเห็น (ไม่บังคับ)" rows="3" placeholder="เล่าประสบการณ์การใช้งานของคุณ..." />
                    <flux:button type="submit" variant="primary">บันทึกรีวิว</flux:button>
                </form>
            @elseif (auth()->user()?->isCustomer())
                <flux:callout icon="information-circle" color="amber">
                    <flux:callout.text>คุณสามารถรีวิวสินค้านี้ได้หลังจากได้รับสินค้าแล้ว (คำสั่งซื้อสถานะ "ได้รับสินค้าแล้ว")</flux:callout.text>
                </flux:callout>
            @endif

            @forelse ($this->reviews as $review)
                <article class="shop-card p-5" wire:key="r-{{ $review->id }}">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$review->user->name" :initials="$review->user->initials()" />
                            <div>
                                <p class="font-medium text-zinc-900">{{ $review->user->name }}</p>
                                <x-stars :rating="$review->rating" size="size-3.5" />
                            </div>
                        </div>
                        <span class="text-xs text-zinc-500">{{ thai_date($review->created_at, false) }}</span>
                    </div>
                    @if ($review->comment)
                        <p class="mt-3 text-zinc-700">{{ $review->comment }}</p>
                    @endif
                </article>
            @empty
                <x-empty-state icon="chat-bubble-left-right" title="ยังไม่มีรีวิว" description="เป็นคนแรกที่รีวิวสินค้านี้หลังได้รับสินค้า" />
            @endforelse
        </div>
    </section>
</div>
