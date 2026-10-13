<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\ShippingMethod;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'SH-'.now()->format('ymd').'-'.strtoupper(Str::random(4)),
            'user_id' => User::factory(),
            'status' => OrderStatus::PendingPayment,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'shipping_fee' => 50,
            'total' => 1050,
            'shipping_method' => ShippingMethod::Standard,
            'ship_name' => fake()->name(),
            'ship_phone' => '0812345678',
            'ship_address' => '1/1 ถนนสุขุมวิท เขตวัฒนา กรุงเทพมหานคร 10110',
        ];
    }
}
