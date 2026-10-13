@props(['product'])
<a href="{{ route('products.show', $product) }}" wire:navigate class="group shop-card flex flex-col overflow-hidden transition hover:-translate-y-0.5 hover:shadow-md">
    <div class="aspect-[4/3] overflow-hidden bg-sand">
        <img src="{{ $product->main_image_url }}" alt="{{ $product->name }}" loading="lazy" class="size-full object-cover transition duration-300 group-hover:scale-105">
    </div>
    <div class="flex flex-1 flex-col gap-1 p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-accent-content">{{ $product->brand->name }}</p>
        <h3 class="line-clamp-2 font-medium text-zinc-900">{{ $product->name }}</h3>
        <div class="flex items-center gap-1.5 text-xs text-zinc-500">
            @if (($product->reviews_count ?? 0) > 0)
                <x-stars :rating="$product->averageRating()" size="size-3.5" />
                <span>{{ number_format($product->averageRating(), 1) }} ({{ $product->reviews_count }})</span>
            @else
                <span>ยังไม่มีรีวิว</span>
            @endif
        </div>
        <p class="mt-auto pt-2 text-lg font-semibold text-brown">{{ baht($product->price) }}</p>
    </div>
</a>
