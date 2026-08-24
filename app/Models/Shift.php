<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $opening_float_millimes
 * @property int|null $closing_cash_counted_millimes
 * @property int $expected_cash_millimes
 * @property int $cash_difference_millimes
 * @property string $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'started_at',
    'ended_at',
    'opening_float_millimes',
    'closing_cash_counted_millimes',
    'expected_cash_millimes',
    'cash_difference_millimes',
    'status',
    'notes',
])]
class Shift extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'opening_float_millimes' => 'integer',
            'closing_cash_counted_millimes' => 'integer',
            'expected_cash_millimes' => 'integer',
            'cash_difference_millimes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public static function active(): ?self
    {
        return static::where('status', 'open')->latest('started_at')->first();
    }
}
