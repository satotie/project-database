<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Product $running;

    private Product $sneaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Address::factory()->for($this->user)->create(['is_default' => true]);
        $this->running = Product::factory()->withSizes(['42'], 10)->create(['price' => 2000]);
        $this->sneaker = Product::factory()->withSizes(['42'], 10)->create(['price' => 1000]);

        foreach ([$this->running, $this->sneaker] as $product) {
            CartItem::create(['user_id' => $this->user->id, 'product_variant_id' => $product->variants->first()->id, 'quantity' => 1]);
        }
    }

    public function test_coupon_discount_applies_only_to_selected_products(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'RUN20', 'discount_percent' => 20, 'applies_to_all' => false]);
        $coupon->products()->attach($this->running);

        Livewire::actingAs($this->user)->test('pages::checkout')
            ->set('couponCode', 'run20')
            ->call('applyCoupon')
            ->assertHasNoErrors()
            ->assertSet('appliedCoupon', 'RUN20')
            ->set('shippingMethod', 'EXPRESS')
            ->call('placeOrder');

        $order = Order::with('items')->first();
        $this->assertEquals(3000, $order->subtotal);
        $this->assertEquals(400, $order->discount_amount); // 20% of the running shoe only
        $this->assertEquals(3000 - 400 + 100, $order->total);
        $this->assertSame($coupon->id, $order->coupon_id);
        $this->assertEquals(400, $order->items->firstWhere('product_name', $this->running->name)->discount_amount);
        $this->assertEquals(0, $order->items->firstWhere('product_name', $this->sneaker->name)->discount_amount);
    }

    public function test_applies_to_all_coupon_discounts_every_item(): void
    {
        Coupon::factory()->create(['code' => 'WELCOME10', 'discount_percent' => 10, 'applies_to_all' => true]);

        Livewire::actingAs($this->user)->test('pages::checkout')
            ->set('couponCode', 'WELCOME10')->call('applyCoupon')->call('placeOrder');

        $this->assertEquals(300, Order::first()->discount_amount);
    }

    public function test_expired_coupon_is_rejected(): void
    {
        Coupon::factory()->create(['code' => 'EXPIRED50', 'discount_percent' => 50, 'expires_at' => now()->subDay()]);

        Livewire::actingAs($this->user)->test('pages::checkout')
            ->set('couponCode', 'EXPIRED50')->call('applyCoupon')
            ->assertHasErrors('couponCode')
            ->assertSet('appliedCoupon', '');
    }

    public function test_unknown_inactive_and_not_started_coupons_are_rejected(): void
    {
        Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
        Coupon::factory()->create(['code' => 'SOON', 'starts_at' => now()->addDay()]);

        foreach (['NOPE', 'OFF', 'SOON'] as $code) {
            Livewire::actingAs($this->user)->test('pages::checkout')
                ->set('couponCode', $code)->call('applyCoupon')->assertHasErrors('couponCode');
        }
    }

    public function test_coupon_below_minimum_order_amount_is_rejected(): void
    {
        Coupon::factory()->create(['code' => 'BIG', 'min_order_amount' => 5000]);

        Livewire::actingAs($this->user)->test('pages::checkout')
            ->set('couponCode', 'BIG')->call('applyCoupon')->assertHasErrors('couponCode');
    }

    public function test_coupon_that_matches_no_cart_item_is_rejected(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'OTHER', 'applies_to_all' => false]);
        $coupon->products()->attach(Product::factory()->create());

        Livewire::actingAs($this->user)->test('pages::checkout')
            ->set('couponCode', 'OTHER')->call('applyCoupon')->assertHasErrors('couponCode');
    }

    public function test_usage_limit_counts_only_non_cancelled_orders(): void
    {
        $coupon = Coupon::factory()->create(['code' => 'ONCE', 'usage_limit' => 1]);
        $other = User::factory()->create();
        $order = Order::factory()->for($other)->create(['coupon_id' => $coupon->id, 'status' => OrderStatus::Cancelled]);

        Livewire::actingAs($this->user)->test('pages::checkout')
            ->set('couponCode', 'ONCE')->call('applyCoupon')->assertHasNoErrors();

        $order->update(['status' => OrderStatus::Paid]);

        Livewire::actingAs($this->user)->test('pages::checkout')
            ->set('couponCode', 'ONCE')->call('applyCoupon')->assertHasErrors('couponCode');
    }
}
