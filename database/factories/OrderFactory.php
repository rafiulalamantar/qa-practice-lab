<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->create();

        return [
            'order_number' => Order::newOrderNumber(),
            'user_id' => User::factory(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_image' => $product->image,
            'product_description' => $product->description,
            'price' => $product->price,
            'country' => 'Bangladesh',
            'ordered_by' => fn (array $attributes) => User::find($attributes['user_id'])->email,
        ];
    }
}
