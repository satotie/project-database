<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ShippingMethod;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    /**
     * Price a list of lines. Each line is ['variant' => ProductVariant (with product), 'quantity' => int].
     *
     * @param  Collection<int, array{variant: ProductVariant, quantity: int}>  $lines
     * @return array{lines: array, subtotal: float, discount: float, shipping_fee: float, total: float, coupon: ?Coupon, coupon_error: ?string}
     */
    public function calculate(Collection $lines, ?Coupon $coupon, ShippingMethod $method): array
    {
        $priced = $lines->map(function (array $line) {
            $unit = (float) $line['variant']->product->price;

            return $line + ['unit_price' => $unit, 'line_total' => round($unit * $line['quantity'], 2), 'discount' => 0.0];
        })->values()->all();

        $subtotal = round(array_sum(array_column($priced, 'line_total')), 2);
        $couponError = null;

        if ($coupon) {
            $couponError = $coupon->validationError($subtotal);

            if (! $couponError) {
                $coupon->loadMissing('products:id');
                $matched = false;

                foreach ($priced as &$line) {
                    if ($coupon->appliesToProduct($line['variant']->product_id)) {
                        // Per-line discount = percent × line total of products linked in coupon_product.
                        $line['discount'] = round($line['line_total'] * $coupon->discount_percent / 100, 2);
                        $matched = true;
                    }
                }
                unset($line);

                if (! $matched) {
                    $couponError = 'คูปองนี้ไม่สามารถใช้กับสินค้าในตะกร้าของคุณได้';
                }
            }

            if ($couponError) {
                $coupon = null;
                foreach ($priced as &$line) {
                    $line['discount'] = 0.0;
                }
                unset($line);
            }
        }

        $discount = round(array_sum(array_column($priced, 'discount')), 2);
        $shippingFee = $method->feeFor($subtotal);

        return [
            'lines' => $priced,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_fee' => $shippingFee,
            'total' => round($subtotal - $discount + $shippingFee, 2),
            'coupon' => $coupon,
            'coupon_error' => $couponError,
        ];
    }

    /** @return Collection<int, array{variant: ProductVariant, quantity: int}> */
    public function cartLines(User $user): Collection
    {
        return $user->cartItems()->with('variant.product')->get()
            ->map(fn (CartItem $item) => ['variant' => $item->variant, 'quantity' => $item->quantity]);
    }

    public function findCoupon(?string $code): ?Coupon
    {
        return filled($code) ? Coupon::where('code', strtoupper(trim($code)))->first() : null;
    }

    /**
     * Place an order from the user's cart inside one transaction:
     * lock variants → check & decrement stock → create order + item snapshots → clear cart.
     */
    public function placeOrder(User $user, ShippingMethod $method, ?Address $address, ?string $couponCode, ?string $note = null): Order
    {
        return DB::transaction(function () use ($user, $method, $address, $couponCode, $note) {
            if ($method->requiresAddress() && (! $address || $address->user_id !== $user->id)) {
                throw ValidationException::withMessages(['address' => 'กรุณาเลือกที่อยู่สำหรับจัดส่ง']);
            }

            $cart = $user->cartItems()->get();

            if ($cart->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'ตะกร้าสินค้าว่างเปล่า']);
            }

            // 1. Lock every variant row so concurrent checkouts cannot oversell.
            $variants = ProductVariant::with('product')
                ->whereIn('id', $cart->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($cart as $item) {
                $variant = $variants[$item->product_variant_id];

                if (! $variant->product->is_active) {
                    throw ValidationException::withMessages(['cart' => "สินค้า {$variant->product->name} ไม่พร้อมจำหน่ายแล้ว"]);
                }
                if ($variant->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "สินค้า {$variant->product->name} ไซซ์ {$variant->size} มีในสต็อกเพียง {$variant->stock} คู่ ไม่พอสำหรับคำสั่งซื้อ",
                    ]);
                }
            }

            $lines = $cart->map(fn (CartItem $item) => ['variant' => $variants[$item->product_variant_id], 'quantity' => $item->quantity]);
            $coupon = $this->findCoupon($couponCode);

            if (filled($couponCode) && ! $coupon) {
                throw ValidationException::withMessages(['coupon' => 'ไม่พบรหัสคูปองนี้']);
            }

            $summary = $this->calculate($lines, $coupon, $method);

            if ($summary['coupon_error']) {
                throw ValidationException::withMessages(['coupon' => $summary['coupon_error']]);
            }

            // 2. Decrement stock.
            foreach ($lines as $line) {
                $line['variant']->decrement('stock', $line['quantity']);
            }

            // 3. Order + item snapshots.
            $order = Order::create([
                'code' => Order::generateCode(),
                'user_id' => $user->id,
                'status' => OrderStatus::PendingPayment,
                'coupon_id' => $summary['coupon']?->id,
                'subtotal' => $summary['subtotal'],
                'discount_amount' => $summary['discount'],
                'shipping_fee' => $summary['shipping_fee'],
                'total' => $summary['total'],
                'shipping_method' => $method,
                'ship_name' => $address?->recipient_name ?? $user->name,
                'ship_phone' => $address?->phone ?? ($user->phone ?? '-'),
                'ship_address' => $method->requiresAddress() ? $address?->fullAddress() : null,
                'note' => $note,
            ]);

            foreach ($summary['lines'] as $line) {
                $order->items()->create([
                    'product_variant_id' => $line['variant']->id,
                    'product_name' => $line['variant']->product->name,
                    'size' => $line['variant']->size,
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'discount_amount' => $line['discount'],
                    'line_total' => $line['line_total'],
                ]);
            }

            // 4. Clear the cart.
            $user->cartItems()->delete();

            return $order;
        });
    }
}
