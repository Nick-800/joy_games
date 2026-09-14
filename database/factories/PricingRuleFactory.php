<?php

namespace Database\Factories;

use App\Models\PricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    public function definition(): array
    {
        return [
            'grace_period_minutes' => 3,
            'minimum_charge_minutes' => 15,
            'rounding_step_minutes' => 5,
            'vip_multiplier' => 1.50,
            'rogue_auto_sleep_seconds' => 120,
            'currency_code' => 'LYD',
            'currency_symbol' => 'د.ل',
            'timezone' => 'Africa/Tripoli',
            'prepaid_overtime_grace_minutes' => 5,
            'allow_overtime_default' => true,
        ];
    }
}
