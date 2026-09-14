<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonImmutable;

/**
 * @property int $id
 * @property int $grace_period_minutes
 * @property int $minimum_charge_minutes
 * @property int $rounding_step_minutes
 * @property float $vip_multiplier
 * @property int $rogue_auto_sleep_seconds
 * @property string $currency_code
 * @property string $currency_symbol
 * @property string $timezone
 * @property int $prepaid_overtime_grace_minutes
 * @property bool $allow_overtime_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'grace_period_minutes',
    'minimum_charge_minutes',
    'rounding_step_minutes',
    'vip_multiplier',
    'rogue_auto_sleep_seconds',
    'currency_code',
    'currency_symbol',
    'timezone',
    'prepaid_overtime_grace_minutes',
    'allow_overtime_default',
])]
class PricingRule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'grace_period_minutes' => 'integer',
            'minimum_charge_minutes' => 'integer',
            'rounding_step_minutes' => 'integer',
            'vip_multiplier' => 'float',
            'rogue_auto_sleep_seconds' => 'integer',
            'prepaid_overtime_grace_minutes' => 'integer',
            'allow_overtime_default' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate([], [
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
        ]);
    }

    public function timezoneInstance(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone ?: 'UTC');
    }
}
