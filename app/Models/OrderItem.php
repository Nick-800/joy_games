<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $game_session_id
 * @property int|null $shift_id
 * @property int $product_id
 * @property string $item_name
 * @property int $unit_price_millimes
 * @property int $quantity
 * @property int $subtotal_millimes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'game_session_id',
    'shift_id',
    'product_id',
    'item_name',
    'unit_price_millimes',
    'quantity',
    'subtotal_millimes',
])]
class OrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit_price_millimes' => 'integer',
            'quantity' => 'integer',
            'subtotal_millimes' => 'integer',
        ];
    }

    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
