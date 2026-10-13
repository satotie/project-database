<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffExtrasTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
    }

    public function test_staff_extra_pages_render(): void
    {
        $this->actingAs($this->staff);
        $this->get(route('staff.users'))->assertOk()->assertSee($this->staff->email);
        $this->get(route('staff.coupons'))->assertOk();
        $this->get(route('staff.reports'))->assertOk()->assertSee('รายงานยอดขาย');
    }

    public function test_staff_can_toggle_user_and_change_role_but_not_themselves(): void
    {
        $customer = User::factory()->create();

        Livewire::actingAs($this->staff)->test('pages::staff.users')
            ->call('toggleActive', $customer->id)
            ->call('changeRole', $customer->id, 'staff')
            ->call('toggleActive', $this->staff->id)->assertHasErrors('user')
            ->call('changeRole', $this->staff->id, 'customer')->assertHasErrors('user');

        $this->assertFalse($customer->fresh()->is_active);
        $this->assertSame('staff', $customer->fresh()->role);
        $this->assertTrue($this->staff->fresh()->is_active);
        $this->assertSame('staff', $this->staff->fresh()->role);
    }

    public function test_staff_can_create_coupon_for_selected_products(): void
    {
        $products = Product::factory(2)->create();

        Livewire::actingAs($this->staff)->test('pages::staff.coupons')
            ->call('create')
            ->set('code', 'sale15')
            ->set('discount_percent', '15')
            ->set('applies_to_all', false)
            ->call('save')
            ->assertHasErrors('productIds')
            ->set('productIds', [(string) $products[0]->id])
            ->call('save')
            ->assertHasNoErrors();

        $coupon = Coupon::where('code', 'SALE15')->firstOrFail();
        $this->assertSame([$products[0]->id], $coupon->products()->pluck('products.id')->all());

        Livewire::actingAs($this->staff)->test('pages::staff.coupons')
            ->set('code', 'BAD')->set('discount_percent', '150')->set('applies_to_all', true)
            ->call('save')->assertHasErrors('discount_percent');
    }

    public function test_sales_report_counts_only_sold_orders(): void
    {
        $brand = Brand::factory()->create(['name' => 'Nike']);
        $category = Category::factory()->create(['name' => 'Running']);
        $product = Product::factory()->withSizes(['42'])->create(['brand_id' => $brand->id, 'category_id' => $category->id]);
        $variant = $product->variants->first();

        $make = function (OrderStatus $status, int $qty) use ($variant) {
            $order = Order::factory()->create(['status' => $status, 'subtotal' => 1000 * $qty, 'discount_amount' => 100, 'shipping_fee' => 0, 'total' => 1000 * $qty - 100]);
            $order->items()->create(['product_variant_id' => $variant->id, 'product_name' => 'X', 'size' => '42', 'unit_price' => 1000, 'quantity' => $qty, 'discount_amount' => 100, 'line_total' => 1000 * $qty]);
        };
        $make(OrderStatus::Paid, 1);
        $make(OrderStatus::Delivered, 2);
        $make(OrderStatus::PendingPayment, 5);
        $make(OrderStatus::Cancelled, 5);

        $report = new SalesReportService(now()->subDays(30), now());
        $summary = $report->summary();

        $this->assertEquals(900 + 1900, $summary['revenue']);
        $this->assertSame(2, $summary['orders']);
        $this->assertEquals(1400, $summary['average']);
        $this->assertEquals(200, $summary['discount']);
        $this->assertEquals(3, $report->topProducts()->first()->quantity);
        $this->assertEquals(2800, $report->revenueByBrand()->firstWhere('name', 'Nike')->revenue);
        $this->assertEquals(2800, $report->revenueByCategory()->firstWhere('name', 'Running')->revenue);
        $this->assertEquals(2800, $report->revenuePerDay()->sum());

        $this->actingAs($this->staff)
            ->get(route('staff.reports.export', ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
