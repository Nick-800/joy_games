<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $station_id
 * @property int|null $game_session_id
 * @property int|null $user_id
 * @property string $event_type
 * @property string $physical_state
 * @property string $expected_state
 * @property array|null $details
 * @property Carbon $created_at
 */
#[Fillable([
    'station_id',
    'game_session_id',
    'user_id',
    'event_type',
    'physical_state',
    'expected_state',
    'details',
    'created_at',
])]
class StationAudit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
