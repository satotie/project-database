@props(['order'])
@php
    use App\Enums\OrderStatus;
    $flow = OrderStatus::flow();
    $currentIndex = array_search($order->status, $flow, true);
    $times = [
        OrderStatus::PendingPayment->value => $order->created_at,
        OrderStatus::Paid->value => $order->paid_at,
        OrderStatus::Preparing->value => null,
        OrderStatus::Shipped->value => $order->shipped_at,
        OrderStatus::Delivered->value => $order->delivered_at,
    ];
@endphp
<div class="shop-card p-5">
    <flux:heading size="lg" class="mb-4">สถานะคำสั่งซื้อ</flux:heading>
    @if ($order->status === OrderStatus::Cancelled)
        <flux:callout variant="danger" icon="x-circle" heading="คำสั่งซื้อถูกยกเลิก" text="เมื่อ {{ thai_date($order->cancelled_at) }}" />
    @else
        <ol class="relative ms-3 border-s-2 border-zinc-200">
            @foreach ($flow as $i => $step)
                @php $done = $i <= $currentIndex; @endphp
                <li class="mb-5 ms-6 last:mb-0">
                    <span @class([
                        'absolute -start-[13px] flex size-6 items-center justify-center rounded-full ring-4 ring-white',
                        'bg-gold text-brown-dark' => $done,
                        'bg-zinc-200 text-zinc-400' => ! $done,
                    ])>
                        @if ($done) <flux:icon.check variant="micro" /> @else <span class="size-2 rounded-full bg-zinc-400"></span> @endif
                    </span>
                    <p @class(['font-medium', 'text-zinc-900' => $done, 'text-zinc-400' => ! $done])>{{ $step->label() }}</p>
                    @if ($done && $times[$step->value])
                        <p class="text-xs text-zinc-500">{{ thai_date($times[$step->value]) }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
