<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CPN####')),
            'description' => 'คูปองส่วนลด',
            'discount_percent' => 10,
            'applies_to_all' => true,
            'min_order_amount' => 0,
            'usage_limit' => null,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
