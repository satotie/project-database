<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Demo checklist run against the real seed data (guest / customer@shop.test / staff@shop.test).
 */
class DemoWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();
    }

    private function customer(): User
    {
        return User::where('email', 'customer@shop.test')->firstOrFail();
    }

    private function staff(): User
    {
        return User::where('email', 'staff@shop.test')->firstOrFail();
    }

    private function inStockVariant(string $category, int $minStock = 2)
    {
        return Product::whereHas('category', fn ($q) => $q->where('name', $category))
            ->with('variants')->get()
            ->flatMap->variants
            ->first(fn ($v) => $v->stock >= $minStock);
    }

    public function test_core_flow_as_guest_then_new_customer(): void
    {
        $product = Product::latest('id')->first();

        // Guest: home, products, product detail
        $this->get('/')->assertOk()->assertSee('สินค้ามาใหม่')->assertSee('ช้อปตามแบรนด์');
        $this->get('/products')->assertOk()->assertSee($product->name);
        $this->get('/products?search=Nike&sort=rating')->assertOk()->assertSee('Nike');
        $this->get(route('products.show', $product))->assertOk()->assertSee($product->name);
        $this->get('/cart')->assertRedirect('/login');

        // Register -> lands on home, as customer
        $this->post(route('register.store'), [
            'name' => 'เพื่อนทดสอบ', 'email' => 'demo@shop.test', 'phone' => '0811112222',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertRedirect('/cart'); // returns to the page the guest tried to open
        $this->assertSame('customer', User::where('email', 'demo@shop.test')->value('role'));
        $this->post(route('logout'));

        // Login
        $this->post(route('login.store'), ['email' => 'customer@shop.test', 'password' => 'password'])->assertRedirect('/');
        $this->get('/account')->assertOk()->assertSee('customer@shop.test')->assertSee('ยอดซื้อสะสม');
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => 'staff@shop.test', 'password' => 'password'])->assertRedirect('/staff/dashboard');
    }

    public function test_run20_only_discounts_running_products(): void
    {
        $user = $this->customer();
        $running = $this->inStockVariant('Running');
        $sneaker = $this->inStockVariant('Sneakers');
        CartItem::create(['user_id' => $user->id, 'product_variant_id' => $running->id, 'quantity' => 1]);
        CartItem::create(['user_id' => $user->id, 'product_variant_id' => $sneaker->id, 'quantity' => 1]);

        $this->actingAs($user)->get('/cart')->assertOk()->assertSee($running->product->name)->assertSee($sneaker->product->name);

        Livewire::actingAs($user)->test('pages::checkout')
            ->set('couponCode', 'EXPIRED50')->call('applyCoupon')
            ->assertHasErrors(['couponCode'])->assertSee('คูปองนี้หมดอายุแล้ว')
            ->set('couponCode', 'RUN20')->call('applyCoupon')->assertHasNoErrors()
            ->call('placeOrder')->assertHasNoErrors();

        $order = Order::latest('id')->with('items')->first();
        $expected = round($running->product->price * 0.2, 2);
        $this->assertEquals($expected, $order->discount_amount);
        $this->assertEquals($expected, $order->items->firstWhere('size', $running->size)->discount_amount ?? $order->items->firstWhere('product_name', $running->product->name)->discount_amount);
        $this->assertEquals(0, $order->items->firstWhere('product_name', $sneaker->product->name)->discount_amount);
    }

    public function test_full_order_lifecycle_until_review(): void
    {
        Storage::fake('public');
        $user = $this->customer();
        $staff = $this->staff();
        $bought = $user->orders()->with('items.variant')->get()->flatMap->items->pluck('variant.product_id');
        $variant = Product::whereNotIn('id', $bought)->with('variants')->get()->flatMap->variants->first(fn ($v) => $v->stock >= 2);
        $product = $variant->product;
        $stockBefore = $variant->stock;
        $product->reviews()->where('user_id', $user->id)->delete();

        // Customer orders
        Livewire::actingAs($user)->test('pages::shop.product', ['product' => $product])
            ->call('selectVariant', $variant->id)->set('quantity', 1)->call('addToCart')->assertHasNoErrors();
        Livewire::actingAs($user)->test('pages::checkout')->set('shippingMethod', 'EXPRESS')->call('placeOrder')->assertHasNoErrors();
        $order = Order::latest('id')->first();
        $this->assertSame($stockBefore - 1, $variant->fresh()->stock);

        // Customer cannot review yet
        $this->assertFalse($user->canReview($product));

        // Upload slip
        Livewire::actingAs($user)->test('pages::orders.payment', ['order' => $order])
            ->set('slip', UploadedFile::fake()->image('slip.png'))->call('upload')->assertHasNoErrors();
        $payment = $order->payments()->first();
        $this->assertSame(PaymentStatus::Pending, $payment->status);

        // Staff: approve -> PREPARING -> SHIPPED (tracking) -> DELIVERED
        $page = Livewire::actingAs($staff)->test('pages::staff.orders.show', ['order' => $order]);
        $page->call('approve', $payment->id);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $page->call('changeStatus', 'PREPARING');
        $page->set('trackingNo', '')->call('changeStatus', 'SHIPPED')->assertHasErrors('tracking_no');
        $page->set('trackingNo', 'TH9998887776')->call('changeStatus', 'SHIPPED')->assertHasNoErrors();
        $this->assertStringContainsString('เลขพัสดุ TH9998887776', $order->fresh()->notificationMessage());
        $page->call('changeStatus', 'DELIVERED');

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->shipped_at);
        $this->assertNotNull($order->delivered_at);

        // Customer sees it and can review with stars
        $this->actingAs($user)->get(route('orders.show', $order))->assertOk()->assertSee('TH9998887776')->assertSee('เขียน/แก้ไขรีวิวสินค้านี้');
        Livewire::actingAs($user)->test('pages::shop.product', ['product' => $product])
            ->assertSee('เขียนรีวิวสินค้านี้')
            ->set('rating', 5)->set('comment', 'ใส่สบายมาก')->call('saveReview')->assertHasNoErrors();
        $this->get(route('products.show', $product))->assertSee('ใส่สบายมาก');
    }

    public function test_error_cases(): void
    {
        $user = $this->customer();
        $variant = $this->inStockVariant('Running');

        // Over stock when adding to cart
        Livewire::actingAs($user)->test('pages::shop.product', ['product' => $variant->product])
            ->call('selectVariant', $variant->id)->set('quantity', $variant->stock + 1)->call('addToCart')
            ->assertHasErrors('quantity');

        // Over stock at checkout (stock dropped after adding)
        CartItem::create(['user_id' => $user->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        $variant->update(['stock' => 1]);
        Livewire::actingAs($user)->test('pages::checkout')->call('placeOrder')->assertHasErrors('cart');
        $this->assertSame(1, $variant->fresh()->stock);
        $variant->update(['stock' => 10]);

        // Customer typing /staff URLs
        $this->actingAs($user);
        foreach (['/staff/dashboard', '/staff/products', '/staff/orders', '/staff/users', '/staff/coupons', '/staff/reports', '/staff/catalog'] as $url) {
            $this->get($url)->assertForbidden();
        }

        // Cancel restores stock
        Livewire::actingAs($user)->test('pages::checkout')->call('placeOrder')->assertHasNoErrors();
        $this->assertSame(8, $variant->fresh()->stock);
        $order = Order::latest('id')->first();
        Livewire::actingAs($user)->test('pages::orders.show', ['order' => $order])->call('cancel');
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $variant->fresh()->stock);

        // Someone else's order
        $other = Order::where('user_id', '!=', $user->id)->first();
        $this->get(route('orders.show', $other))->assertForbidden();
    }

    public function test_staff_pages_with_seed_data(): void
    {
        $this->actingAs($this->staff());
        $this->get('/staff/dashboard')->assertOk()->assertSee('รายได้วันนี้');
        $this->get('/staff/users')->assertOk()->assertSee('customer@shop.test');
        $this->get('/staff/products')->assertOk()->assertSee(Product::latest('id')->value('name'));
        $this->get('/staff/orders')->assertOk();
        $this->get('/staff/coupons')->assertOk()->assertSee('RUN20');
        $this->get('/staff/reports')->assertOk()->assertSee('สินค้าขายดี 10 อันดับ');
        $this->get('/staff/catalog')->assertOk()->assertSee('Asics');
    }
}
