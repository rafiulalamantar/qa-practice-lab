<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => strtoupper(fake()->unique()->words(2, true)),
            'category' => 'fashion',
            'sub_category' => 't-shirts',
            'product_for' => 'men',
            'price' => fake()->numberBetween(10, 500),
            'description' => fake()->sentence(),
            'image' => 'images/products/tee-classic.svg',
        ];
    }
}
