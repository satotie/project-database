<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ShippingMethod;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
    }

    private function orderWithItem(OrderStatus $status = OrderStatus::PendingPayment, int $stock = 3, int $qty = 2, ShippingMethod $method = ShippingMethod::Standard): array
    {
        $variant = Product::factory()->withSizes(['42'], $stock)->create()->variants->first();
        $order = Order::factory()->create(['status' => $status, 'shipping_method' => $method]);
        $order->items()->create([
            'product_variant_id' => $variant->id, 'product_name' => 'X', 'size' => '42',
            'unit_price' => 500, 'quantity' => $qty, 'line_total' => 500 * $qty,
        ]);

        return [$order, $variant];
    }

    public function test_customer_cannot_access_any_staff_page(): void
    {
        $customer = User::factory()->create();
        foreach (['staff.dashboard', 'staff.products.index', 'staff.orders.index', 'staff.users', 'staff.coupons', 'staff.reports', 'staff.catalog'] as $route) {
            $this->actingAs($customer)->get(route($route))->assertForbidden();
        }
    }

    public function test_staff_pages_render(): void
    {
        [$order] = $this->orderWithItem();
        $this->actingAs($this->staff);

        foreach (['staff.dashboard', 'staff.products.index', 'staff.products.create', 'staff.orders.index', 'staff.catalog'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('staff.orders.show', $order))->assertOk()->assertSee($order->code);
        $this->get(route('staff.products.edit', Product::first()))->assertOk();
    }

    public function test_staff_can_create_product_with_variants_and_images(): void
    {
        Storage::fake('public');
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();

        Livewire::actingAs($this->staff)->test('pages::staff.products.form')
            ->set('name', 'Super Runner')
            ->set('slug', 'super-runner')
            ->set('brand_id', $brand->id)
            ->set('category_id', $category->id)
            ->set('gender', 'men')
            ->set('price', '2590')
            ->set('description', 'รองเท้าวิ่งรุ่นใหม่')
            ->set('variants', [
                ['id' => null, 'size' => '41', 'stock' => 5, 'sku' => 'SR-41'],
                ['id' => null, 'size' => '42', 'stock' => 0, 'sku' => 'SR-42'],
            ])
            ->set('uploads', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')])
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::where('slug', 'super-runner')->firstOrFail();
        $this->assertEquals(2590, $product->price);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(2, $product->images()->count());
        Storage::disk('public')->assertExists($product->images->first()->path);
    }

    public function test_product_variant_validation(): void
    {
        Livewire::actingAs($this->staff)->test('pages::staff.products.form')
            ->set('variants', [
                ['id' => null, 'size' => '41', 'stock' => -1, 'sku' => 'A'],
                ['id' => null, 'size' => '41', 'stock' => 1, 'sku' => 'A'],
            ])
            ->call('save')
            ->assertHasErrors(['name', 'brand_id', 'price', 'variants.0.stock', 'variants.0.size', 'variants.0.sku']);
    }

    public function test_deleting_product_with_orders_only_disables_it(): void
    {
        [, $variant] = $this->orderWithItem();
        $product = $variant->product;
        $fresh = Product::factory()->create();

        Livewire::actingAs($this->staff)->test('pages::staff.products.index')
            ->call('delete', $product->id)
            ->call('delete', $fresh->id);

        $this->assertFalse($product->fresh()->is_active);
        $this->assertModelMissing($fresh);
    }

    public function test_staff_approves_payment_and_order_becomes_paid(): void
    {
        [$order] = $this->orderWithItem();
        $payment = $order->payments()->create(['slip_path' => 'slips/x.jpg', 'amount' => $order->total]);

        Livewire::actingAs($this->staff)->test('pages::staff.orders.show', ['order' => $order])
            ->call('approve', $payment->id);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(PaymentStatus::Approved, $payment->fresh()->status);
        $this->assertSame($this->staff->id, $payment->fresh()->verified_by);
    }

    public function test_staff_rejects_payment_and_customer_can_upload_again(): void
    {
        [$order] = $this->orderWithItem();
        $payment = $order->payments()->create(['slip_path' => 'slips/x.jpg', 'amount' => $order->total]);

        Livewire::actingAs($this->staff)->test('pages::staff.orders.show', ['order' => $order])
            ->call('startReject', $payment->id)
            ->call('reject')
            ->assertHasErrors('rejectReason')
            ->set('rejectReason', 'ยอดไม่ตรง')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertSame(PaymentStatus::Rejected, $payment->fresh()->status);
        $this->assertSame('ยอดไม่ตรง', $payment->fresh()->reject_reason);
        $this->assertSame(OrderStatus::PendingPayment, $order->fresh()->status);
        $this->assertTrue($order->fresh()->load('payments')->canUploadSlip());
    }

    public function test_status_transitions_are_enforced_and_shipping_needs_tracking(): void
    {
        [$order] = $this->orderWithItem(OrderStatus::Paid);
        $page = Livewire::actingAs($this->staff)->test('pages::staff.orders.show', ['order' => $order]);

        $page->call('changeStatus', 'DELIVERED')->assertHasErrors('status');
        $page->call('changeStatus', 'PREPARING')->assertHasNoErrors();
        $page->set('trackingNo', '')->call('changeStatus', 'SHIPPED')->assertHasErrors('tracking_no');
        $this->assertSame(OrderStatus::Preparing, $order->fresh()->status);

        $page->set('trackingNo', 'TH123')->call('changeStatus', 'SHIPPED')->assertHasNoErrors();
        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
        $this->assertSame('TH123', $order->tracking_no);
        $this->assertNotNull($order->shipped_at);
        $this->assertStringContainsString('เลขพัสดุ TH123', $order->notificationMessage());

        $page->call('changeStatus', 'CANCELLED')->assertHasErrors('status');
        $page->call('changeStatus', 'DELIVERED');
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_pickup_orders_can_ship_without_tracking(): void
    {
        [$order] = $this->orderWithItem(OrderStatus::Preparing, method: ShippingMethod::Pickup);

        Livewire::actingAs($this->staff)->test('pages::staff.orders.show', ['order' => $order])
            ->call('changeStatus', 'SHIPPED')->assertHasNoErrors();

        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    public function test_staff_cancel_restores_stock(): void
    {
        [$order, $variant] = $this->orderWithItem(OrderStatus::Preparing, stock: 3, qty: 2);

        Livewire::actingAs($this->staff)->test('pages::staff.orders.show', ['order' => $order])
            ->call('changeStatus', 'CANCELLED');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_staff_can_manage_brands_and_categories(): void
    {
        Livewire::actingAs($this->staff)->test('pages::staff.catalog')
            ->set('brandName', 'Puma')->call('saveBrand')->assertHasNoErrors()
            ->set('brandName', 'Puma')->call('saveBrand')->assertHasErrors('brandName')
            ->set('categoryName', 'Training')->call('saveCategory')->assertHasNoErrors();

        $this->assertDatabaseHas('brands', ['name' => 'Puma']);
        $this->assertDatabaseHas('categories', ['name' => 'Training', 'slug' => 'training']);

        $brand = Brand::factory()->create();
        Product::factory()->create(['brand_id' => $brand->id]);
        Livewire::actingAs($this->staff)->test('pages::staff.catalog')->call('deleteBrand', $brand->id);
        $this->assertModelExists($brand);
    }
}
