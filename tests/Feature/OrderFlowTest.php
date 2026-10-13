<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(User $user, int $stock = 5, int $quantity = 2): array
    {
        Address::factory()->for($user)->create(['is_default' => true]);
        $variant = Product::factory()->withSizes(['42'], $stock)->create()->variants->first();
        CartItem::create(['user_id' => $user->id, 'product_variant_id' => $variant->id, 'quantity' => $quantity]);
        Livewire::actingAs($user)->test('pages::checkout')->call('placeOrder')->assertHasNoErrors();

        return [Order::latest('id')->first(), $variant];
    }

    public function test_customer_can_cancel_pending_order_and_stock_is_restored(): void
    {
        $user = User::factory()->create();
        [$order, $variant] = $this->placeOrder($user, stock: 5, quantity: 2);
        $this->assertSame(3, $variant->fresh()->stock);

        Livewire::actingAs($user)->test('pages::orders.show', ['order' => $order])->call('cancel');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_customer_cannot_cancel_after_payment(): void
    {
        $user = User::factory()->create();
        [$order] = $this->placeOrder($user);
        $order->update(['status' => OrderStatus::Paid]);

        Livewire::actingAs($user)->test('pages::orders.show', ['order' => $order])->call('cancel')->assertForbidden();
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_customer_uploads_slip_creates_pending_payment(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        [$order] = $this->placeOrder($user);

        Livewire::actingAs($user)->test('pages::orders.payment', ['order' => $order])
            ->set('slip', UploadedFile::fake()->image('slip.jpg'))
            ->call('upload')
            ->assertHasNoErrors();

        $payment = $order->payments()->first();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertEquals($order->total, $payment->amount);
        Storage::disk('public')->assertExists($payment->slip_path);

        // A second upload is blocked while one is waiting for review.
        Livewire::actingAs($user)->test('pages::orders.payment', ['order' => $order])
            ->set('slip', UploadedFile::fake()->image('slip2.jpg'))
            ->call('upload')
            ->assertForbidden();
    }

    public function test_slip_must_be_an_image_up_to_4mb(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        [$order] = $this->placeOrder($user);

        Livewire::actingAs($user)->test('pages::orders.payment', ['order' => $order])
            ->set('slip', UploadedFile::fake()->image('slip.gif'))
            ->call('upload')
            ->assertHasErrors('slip');

        Livewire::actingAs($user)->test('pages::orders.payment', ['order' => $order])
            ->set('slip', UploadedFile::fake()->image('big.png')->size(5000))
            ->call('upload')
            ->assertHasErrors('slip');
    }

    public function test_customer_pages_render(): void
    {
        $user = User::factory()->create();
        [$order] = $this->placeOrder($user);

        $this->actingAs($user);
        $this->get(route('orders.index'))->assertOk()->assertSee($order->code);
        $this->get(route('orders.show', $order))->assertOk();
        $this->get(route('orders.payment', $order))->assertOk()->assertSee('123-4-56789-0');
        $this->get(route('account'))->assertOk()->assertSee($user->email);
    }

    public function test_customer_can_manage_addresses_but_not_others(): void
    {
        $user = User::factory()->create();
        $other = Address::factory()->create();

        Livewire::actingAs($user)->test('pages::account')
            ->set('form.recipient_name', 'ทดสอบ')
            ->set('form.phone', '0812345678')
            ->set('form.address_line', '99 ถนนพระราม 4')
            ->set('form.district', 'ปทุมวัน')
            ->set('form.province', 'กรุงเทพมหานคร')
            ->set('form.postal_code', '10330')
            ->call('saveAddress')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'postal_code' => '10330', 'is_default' => true]);

        Livewire::actingAs($user)->test('pages::account')->call('deleteAddress', $other->id)->assertForbidden();
        $this->assertModelExists($other);
    }
}
