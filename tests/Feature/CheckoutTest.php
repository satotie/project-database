<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function customerWithCart(int $stock = 5, int $quantity = 2, int $price = 1000): array
    {
        $user = User::factory()->create();
        $address = Address::factory()->for($user)->create(['is_default' => true]);
        $product = Product::factory()->withSizes(['42'], $stock)->create(['price' => $price]);
        $variant = $product->variants->first();
        CartItem::create(['user_id' => $user->id, 'product_variant_id' => $variant->id, 'quantity' => $quantity]);

        return [$user, $address, $variant];
    }

    public function test_guest_cannot_access_cart_or_checkout(): void
    {
        $this->get(route('cart'))->assertRedirect(route('login'));
        $this->get(route('checkout'))->assertRedirect(route('login'));
    }

    public function test_cart_page_shows_items(): void
    {
        [$user, , $variant] = $this->customerWithCart();

        $this->actingAs($user)->get(route('cart'))->assertOk()->assertSee($variant->product->name);
    }

    public function test_checkout_creates_order_with_snapshots_and_decrements_stock(): void
    {
        [$user, $address, $variant] = $this->customerWithCart(stock: 5, quantity: 2, price: 1000);

        Livewire::actingAs($user)->test('pages::checkout')
            ->set('shippingMethod', 'EXPRESS')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::first();
        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertEquals(2000, $order->subtotal);
        $this->assertEquals(100, $order->shipping_fee);
        $this->assertEquals(2100, $order->total);
        $this->assertSame($address->recipient_name, $order->ship_name);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'product_name' => $variant->product->name, 'size' => '42', 'unit_price' => 1000, 'quantity' => 2, 'line_total' => 2000,
        ]);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertMatchesRegularExpression('/^SH-\d{6}-[A-Z0-9]{4}$/', $order->code);
    }

    public function test_checkout_fails_when_stock_is_insufficient_and_saves_nothing(): void
    {
        [$user, , $variant] = $this->customerWithCart(stock: 5, quantity: 2);
        $variant->update(['stock' => 1]); // someone else bought it meanwhile

        Livewire::actingAs($user)->test('pages::checkout')
            ->call('placeOrder')
            ->assertHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame(1, $variant->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_standard_shipping_is_free_from_3000(): void
    {
        [$user] = $this->customerWithCart(quantity: 3, price: 1000);

        Livewire::actingAs($user)->test('pages::checkout')->set('shippingMethod', 'STANDARD')->call('placeOrder');

        $this->assertEquals(0, Order::first()->shipping_fee);
    }

    public function test_pickup_does_not_require_address(): void
    {
        $user = User::factory()->create();
        $variant = Product::factory()->withSizes(['42'], 5)->create(['price' => 500])->variants->first();
        CartItem::create(['user_id' => $user->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        Livewire::actingAs($user)->test('pages::checkout')->set('shippingMethod', 'PICKUP')->call('placeOrder')->assertHasNoErrors();

        $order = Order::first();
        $this->assertNull($order->ship_address);
        $this->assertEquals(0, $order->shipping_fee);
        $this->assertEquals(500, $order->total);
    }

    public function test_delivery_requires_an_address(): void
    {
        $user = User::factory()->create();
        $variant = Product::factory()->withSizes(['42'], 5)->create()->variants->first();
        CartItem::create(['user_id' => $user->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        Livewire::actingAs($user)->test('pages::checkout')->set('shippingMethod', 'STANDARD')->call('placeOrder')->assertHasErrors('address');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        [$user] = $this->customerWithCart();
        Livewire::actingAs($user)->test('pages::checkout')->call('placeOrder');
        $order = Order::first();

        $this->actingAs(User::factory()->create())->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($user)->get(route('orders.show', $order))->assertOk()->assertSee($order->code);
    }

    public function test_staff_cannot_use_customer_pages(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get(route('cart'))->assertForbidden();
    }
}
