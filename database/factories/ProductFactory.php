<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = 'รองเท้า '.fake()->unique()->words(2, true);

        return [
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug(fake()->unique()->words(3, true)).'-'.Str::lower(Str::random(4)),
            'description' => 'รองเท้าคุณภาพดี ใส่สบาย',
            'price' => fake()->numberBetween(13, 69) * 100 - 10,
            'gender' => fake()->randomElement(['men', 'women', 'unisex']),
            'is_active' => true,
        ];
    }

    /** Create one variant per size with the given stock. */
    public function withSizes(array $sizes = ['40', '41', '42'], int $stock = 10): static
    {
        return $this->afterCreating(function (Product $product) use ($sizes, $stock) {
            foreach ($sizes as $size) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'size' => $size,
                    'stock' => $stock,
                    'sku' => 'SKU-'.$product->id.'-'.$size,
                ]);
            }
        });
    }
}
