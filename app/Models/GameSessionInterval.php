<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_session_id
 * @property int $pricing_tier_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $duration_seconds
 * @property int $billable_minutes
 * @property int $rate_per_hour_millimes
 * @property float $station_multiplier
 * @property int $subtotal_millimes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_session_id',
    'pricing_tier_id',
    'started_at',
    'ended_at',
    'duration_seconds',
    'billable_minutes',
    'rate_per_hour_millimes',
    'station_multiplier',
    'subtotal_millimes',
])]
class GameSessionInterval extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'billable_minutes' => 'integer',
            'rate_per_hour_millimes' => 'integer',
            'station_multiplier' => 'float',
            'subtotal_millimes' => 'integer',
        ];
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function pricingTier(): BelongsTo
    {
        return $this->belongsTo(PricingTier::class);
    }
}
