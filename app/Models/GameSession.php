<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $station_id
 * @property int|null $shift_id
 * @property int $cashier_id
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string $session_type
 * @property string $status
 * @property int|null $allocated_minutes
 * @property bool $allow_overtime
 * @property Carbon $started_at
 * @property Carbon|null $paused_at
 * @property int $total_paused_seconds
 * @property string|null $pause_reason
 * @property Carbon|null $ended_at
 * @property int $time_amount_millimes
 * @property int $retail_amount_millimes
 * @property int $discount_amount_millimes
 * @property int $final_total_millimes
 * @property string $payment_status
 * @property string|null $payment_method
 * @property int|null $cash_received_millimes
 * @property int|null $cash_change_millimes
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'station_id',
    'shift_id',
    'cashier_id',
    'customer_name',
    'customer_phone',
    'session_type',
    'status',
    'allocated_minutes',
    'allow_overtime',
    'started_at',
    'paused_at',
    'total_paused_seconds',
    'pause_reason',
    'ended_at',
    'time_amount_millimes',
    'retail_amount_millimes',
    'discount_amount_millimes',
    'final_total_millimes',
    'payment_status',
    'payment_method',
    'cash_received_millimes',
    'cash_change_millimes',
    'notes',
])]
class GameSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'allocated_minutes' => 'integer',
            'allow_overtime' => 'boolean',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'total_paused_seconds' => 'integer',
            'ended_at' => 'datetime',
            'time_amount_millimes' => 'integer',
            'retail_amount_millimes' => 'integer',
            'discount_amount_millimes' => 'integer',
            'final_total_millimes' => 'integer',
            'cash_received_millimes' => 'integer',
            'cash_change_millimes' => 'integer',
        ];
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(GameSessionInterval::class);
    }

    public function activeInterval(): HasOne
    {
        return $this->hasOne(GameSessionInterval::class)
            ->whereNull('ended_at')
            ->latestOfMany();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(StationAudit::class);
    }

    public function isPrepaid(): bool
    {
        return $this->session_type === 'prepaid';
    }

    public function isPostpaid(): bool
    {
        return $this->session_type === 'postpaid';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isPaymentPending(): bool
    {
        return $this->status === 'payment_pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
