<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $controller_count_min
 * @property int $controller_count_max
 * @property int $hourly_rate_millimes
 * @property int $display_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'controller_count_min',
    'controller_count_max',
    'hourly_rate_millimes',
    'display_order',
    'is_active',
])]
class PricingTier extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'controller_count_min' => 'integer',
            'controller_count_max' => 'integer',
            'hourly_rate_millimes' => 'integer',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(GameSessionInterval::class);
    }

    public function getHourlyRateLydAttribute(): float
    {
        return $this->hourly_rate_millimes / 1000;
    }
}
