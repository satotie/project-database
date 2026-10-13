<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
        'applies_to_all' => false,
        'min_order_amount' => 0,
    ];

    protected $fillable = [
        'code', 'description', 'discount_percent', 'applies_to_all', 'min_order_amount',
        'usage_limit', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'integer',
            'applies_to_all' => 'boolean',
            'min_order_amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected function code(): Attribute
    {
        return Attribute::set(fn (string $value) => strtoupper(trim($value)));
    }

    /** M:N via coupon_product. */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Times used = orders with this coupon that were not cancelled. */
    public function usageCount(): int
    {
        return $this->orders()->where('status', '!=', OrderStatus::Cancelled->value)->count();
    }

    /**
     * Returns a Thai error message when the coupon cannot be used, or null when valid.
     */
    public function validationError(float $subtotal): ?string
    {
        $now = now();

        if (! $this->is_active) {
            return 'คูปองนี้ถูกปิดการใช้งานแล้ว';
        }
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return 'คูปองนี้ยังไม่เริ่มใช้งาน';
        }
        if ($this->expires_at && $now->gt($this->expires_at)) {
            return 'คูปองนี้หมดอายุแล้ว';
        }
        if ($subtotal < (float) $this->min_order_amount) {
            return 'ยอดสั่งซื้อขั้นต่ำสำหรับคูปองนี้คือ ฿'.number_format((float) $this->min_order_amount);
        }
        if ($this->usage_limit !== null && $this->usageCount() >= $this->usage_limit) {
            return 'คูปองนี้ถูกใช้ครบจำนวนสิทธิ์แล้ว';
        }

        return null;
    }

    public function appliesToProduct(int $productId): bool
    {
        if ($this->applies_to_all) {
            return true;
        }

        $ids = $this->relationLoaded('products')
            ? $this->products->modelKeys()
            : $this->products()->pluck('products.id')->all();

        return in_array($productId, $ids, true);
    }
}
