<?php

namespace App\Models;

use App\Services\Billing\RateEngine;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $station_number
 * @property string $type
 * @property string|null $tv_ip_address
 * @property string|null $tv_mac_address
 * @property string $tv_os_type
 * @property string|null $tv_auth_token
 * @property string $tv_physical_state
 * @property string $current_state
 * @property int $consecutive_on_pings
 * @property Carbon|null $first_detected_on_at
 * @property Carbon|null $last_ping_at
 * @property string|null $notes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'station_number',
    'type',
    'default_hourly_rate_millimes',
    'hourly_rate_1_2_millimes',
    'hourly_rate_3_4_millimes',
    'available_games',
    'tv_ip_address',
    'tv_mac_address',
    'tv_os_type',
    'tv_auth_token',
    'tv_physical_state',
    'current_state',
    'consecutive_on_pings',
    'first_detected_on_at',
    'last_ping_at',
    'notes',
    'is_active',
])]
class Station extends Model
{
    use HasFactory;

    protected $appends = ['default_hourly_rate_lyd', 'hourly_rate_1_2_lyd', 'hourly_rate_3_4_lyd'];

    protected function casts(): array
    {
        return [
            'station_number' => 'integer',
            'default_hourly_rate_millimes' => 'integer',
            'hourly_rate_1_2_millimes' => 'integer',
            'hourly_rate_3_4_millimes' => 'integer',
            'available_games' => 'array',
            'consecutive_on_pings' => 'integer',
            'first_detected_on_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getDefaultHourlyRateLydAttribute(): int
    {
        return (int) round(($this->default_hourly_rate_millimes ?? 10000) / 1000);
    }

    public function getHourlyRate12LydAttribute(): ?int
    {
        if ($this->hourly_rate_1_2_millimes !== null) {
            return (int) round($this->hourly_rate_1_2_millimes / 1000);
        }

        return $this->default_hourly_rate_millimes !== null ? (int) round($this->default_hourly_rate_millimes / 1000) : null;
    }

    public function getHourlyRate34LydAttribute(): ?int
    {
        if ($this->hourly_rate_3_4_millimes !== null) {
            return (int) round($this->hourly_rate_3_4_millimes / 1000);
        }

        return null;
    }

    public function getHourlyRateForTier(PricingTier $tier): int
    {
        if ($tier->controller_count_max <= 2 && $this->hourly_rate_1_2_millimes !== null) {
            return $this->hourly_rate_1_2_millimes;
        }

        if ($tier->controller_count_min >= 3 && $this->hourly_rate_3_4_millimes !== null) {
            return $this->hourly_rate_3_4_millimes;
        }

        if ($this->default_hourly_rate_millimes !== null) {
            return $this->default_hourly_rate_millimes;
        }

        $rule = PricingRule::current();
        $multiplier = $this->isVip() ? (float) ($rule->vip_multiplier ?? 1.50) : 1.00;
        $raw = (int) round($tier->hourly_rate_millimes * $multiplier);

        return RateEngine::roundUpToMultipleOfFive($raw);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(GameSession::class)
            ->whereIn('status', ['active', 'paused', 'payment_pending'])
            ->latestOfMany();
    }

    public function audits(): HasMany
    {
        return $this->hasMany(StationAudit::class);
    }

    public function isAvailable(): bool
    {
        return $this->current_state === 'available';
    }

    public function isVip(): bool
    {
        return $this->type === 'vip';
    }

    public function isScreenOn(): bool
    {
        return $this->tv_physical_state === 'screen_on';
    }

    public function isRogue(): bool
    {
        $rule = PricingRule::current();
        if ($rule && ! $rule->tv_control_enabled) {
            return false;
        }

        return $this->current_state === 'available'
            && $this->tv_physical_state === 'screen_on'
            && $this->consecutive_on_pings >= 2;
    }
}
