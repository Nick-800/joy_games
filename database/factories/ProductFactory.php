<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->randomElement([
            'Red Bull 250ml', 'Monster 355ml', 'Coca-Cola 330ml',
            'Water 500ml', 'Doritos 75g', 'Pringles 110g',
            'KitKat 4 Finger', 'Snickers 50g',
        ]);

        return [
            'name' => $name,
            'category' => fake()->randomElement(['beverage', 'snack']),
            'price_millimes' => fake()->randomElement([1000, 2000, 2500, 3000, 4500, 5000, 6000]),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }
}
