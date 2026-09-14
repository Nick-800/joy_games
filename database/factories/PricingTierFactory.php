<?php

namespace Database\Factories;

use App\Models\PricingTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingTier>
 */
class PricingTierFactory extends Factory
{
    protected $model = PricingTier::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['1–2 Players', '3–4 Players', '5+ Players']).' ('.fake()->word().')',
            'controller_count_min' => 1,
            'controller_count_max' => 2,
            'hourly_rate_millimes' => fake()->randomElement([6000, 10000, 12000, 15000]),
            'display_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
