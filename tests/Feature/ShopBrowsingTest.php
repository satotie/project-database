<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShopBrowsingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_home_and_products(): void
    {
        $product = Product::factory()->withSizes()->create(['name' => 'Test Runner']);

        $this->get(route('home'))->assertOk()->assertSee('Test Runner');
        $this->get(route('products.index'))->assertOk()->assertSee('Test Runner');
        $this->get(route('products.show', $product))->assertOk()->assertSee('Test Runner')->assertSee('42');
    }

    public function test_inactive_products_are_hidden_from_guests(): void
    {
        $product = Product::factory()->create(['name' => 'Hidden Shoe', 'is_active' => false]);

        $this->get(route('products.index'))->assertDontSee('Hidden Shoe');
        $this->get(route('products.show', $product))->assertNotFound();
    }

    public function test_products_can_be_filtered_by_size_in_stock(): void
    {
        Product::factory()->withSizes(['38'], 5)->create(['name' => 'Small Shoe']);
        Product::factory()->withSizes(['44'], 0)->create(['name' => 'Empty Big Shoe']);

        Livewire::test('pages::shop.products')
            ->set('size', '38')
            ->assertSee('Small Shoe')
            ->assertDontSee('Empty Big Shoe')
            ->set('size', '44')
            ->assertDontSee('Empty Big Shoe');
    }

    public function test_guest_adding_to_cart_is_redirected_to_login(): void
    {
        $product = Product::factory()->withSizes()->create();

        Livewire::test('pages::shop.product', ['product' => $product])
            ->call('selectVariant', $product->variants->first()->id)
            ->call('addToCart')
            ->assertRedirect(route('login'));

        $this->assertSame(route('products.show', $product), session('url.intended'));
    }

    public function test_customer_must_pick_a_size_and_cannot_exceed_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withSizes(['42'], 2)->create();
        $variant = $product->variants->first();

        Livewire::actingAs($user)->test('pages::shop.product', ['product' => $product])
            ->call('addToCart')
            ->assertHasErrors('variantId')
            ->call('selectVariant', $variant->id)
            ->set('quantity', 2)
            ->call('addToCart')
            ->assertHasNoErrors()
            ->set('quantity', 1)
            ->call('addToCart')
            ->assertHasErrors('quantity');

        $this->assertDatabaseHas('cart_items', ['user_id' => $user->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
    }

    public function test_adding_same_variant_increases_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->withSizes(['42'], 10)->create();
        $variant = $product->variants->first();

        $page = Livewire::actingAs($user)->test('pages::shop.product', ['product' => $product])->call('selectVariant', $variant->id);
        $page->set('quantity', 1)->call('addToCart');
        $page->set('quantity', 2)->call('addToCart');

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['product_variant_id' => $variant->id, 'quantity' => 3]);
    }
}
