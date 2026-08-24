<?php

namespace App\Models;

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

    protected function casts(): array
    {
        return [
            'station_number' => 'integer',
            'consecutive_on_pings' => 'integer',
            'first_detected_on_at' => 'datetime',
            'last_ping_at' => 'datetime',
            'is_active' => 'boolean',
        ];
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
        return $this->current_state === 'available'
            && $this->tv_physical_state === 'screen_on'
            && $this->consecutive_on_pings >= 2;
    }
}
