<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CartService
{
    /** Add a variant; adding the same variant again increases the quantity (capped by stock). */
    public function add(User $user, ProductVariant $variant, int $quantity): CartItem
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'จำนวนต้องมากกว่า 0']);
        }
        if (! $variant->product->is_active) {
            throw ValidationException::withMessages(['variant' => 'สินค้านี้ไม่พร้อมจำหน่าย']);
        }

        $item = CartItem::firstOrNew(['user_id' => $user->id, 'product_variant_id' => $variant->id]);
        $newQuantity = ($item->exists ? $item->quantity : 0) + $quantity;

        if ($newQuantity > $variant->stock) {
            $inCart = $item->exists ? " (ในตะกร้ามีแล้ว {$item->quantity} คู่)" : '';

            throw ValidationException::withMessages([
                'quantity' => "ไซซ์ {$variant->size} มีสินค้าเหลือเพียง {$variant->stock} คู่{$inCart}",
            ]);
        }

        $item->quantity = $newQuantity;
        $item->save();

        return $item;
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        $stock = $item->variant->stock;

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'จำนวนต้องมากกว่า 0']);
        }
        if ($quantity > $stock) {
            throw ValidationException::withMessages(['quantity' => "สินค้าไซซ์ {$item->variant->size} มีเหลือเพียง {$stock} คู่"]);
        }

        $item->update(['quantity' => $quantity]);
    }
}
