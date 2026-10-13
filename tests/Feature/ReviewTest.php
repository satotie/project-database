<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function buy(User $user, Product $product, OrderStatus $status): void
    {
        $order = Order::factory()->for($user)->create(['status' => $status]);
        $order->items()->create([
            'product_variant_id' => $product->variants->first()->id, 'product_name' => $product->name, 'size' => '42',
            'unit_price' => 1000, 'quantity' => 1, 'line_total' => 1000,
        ]);
    }

    public function test_customer_cannot_review_without_a_delivered_order(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withSizes()->create();
        $this->buy($user, $product, OrderStatus::Shipped);

        Livewire::actingAs($user)->test('pages::shop.product', ['product' => $product])
            ->assertDontSee('เขียนรีวิวสินค้านี้')
            ->set('rating', 5)
            ->call('saveReview')
            ->assertForbidden();

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_customer_can_review_after_delivery_and_edit_it(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withSizes()->create();
        $this->buy($user, $product, OrderStatus::Delivered);

        Livewire::actingAs($user)->test('pages::shop.product', ['product' => $product])
            ->assertSee('เขียนรีวิวสินค้านี้')
            ->set('rating', 4)->set('comment', 'ดีมาก')
            ->call('saveReview')->assertHasNoErrors();

        Livewire::actingAs($user)->test('pages::shop.product', ['product' => $product])
            ->assertSet('rating', 4)
            ->set('rating', 2)->set('comment', 'เปลี่ยนใจ')
            ->call('saveReview');

        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'product_id' => $product->id, 'rating' => 2, 'comment' => 'เปลี่ยนใจ']);
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withSizes()->create();
        $this->buy($user, $product, OrderStatus::Delivered);

        Livewire::actingAs($user)->test('pages::shop.product', ['product' => $product])
            ->set('rating', 6)->call('saveReview')->assertHasErrors('rating')
            ->set('rating', 0)->call('saveReview')->assertHasErrors('rating');
    }

    public function test_product_shows_average_rating_and_can_sort_by_rating(): void
    {
        $low = Product::factory()->create(['name' => 'Low Rated']);
        $high = Product::factory()->create(['name' => 'High Rated']);
        Review::factory()->create(['product_id' => $low->id, 'rating' => 2]);
        Review::factory()->create(['product_id' => $high->id, 'rating' => 5]);
        Review::factory()->create(['product_id' => $high->id, 'rating' => 4]);

        $this->assertSame(4.5, $high->averageRating());
        $this->get(route('products.show', $high))->assertSee('4.5')->assertSee('2 รีวิว');

        Livewire::test('pages::shop.products')->set('sort', 'rating')->assertSeeInOrder(['High Rated', 'Low Rated']);
    }
}
