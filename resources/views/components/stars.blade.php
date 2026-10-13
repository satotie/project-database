@props(['rating' => 0, 'size' => 'size-4'])
{{-- Star display: full, half and empty stars for a 0–5 rating. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center text-gold']) }} aria-label="คะแนน {{ number_format((float) $rating, 1) }} จาก 5">
    @for ($i = 1; $i <= 5; $i++)
        @if ($rating >= $i)
            <flux:icon.star variant="solid" class="{{ $size }}" />
        @elseif ($rating >= $i - 0.5)
            <span class="relative inline-block {{ $size }}">
                <flux:icon.star variant="solid" class="{{ $size }} text-zinc-300" />
                <span class="absolute inset-0 w-1/2 overflow-hidden"><flux:icon.star variant="solid" class="{{ $size }}" /></span>
            </span>
        @else
            <flux:icon.star variant="solid" class="{{ $size }} text-zinc-300" />
        @endif
    @endfor
</span>
