<?php

namespace App\Services\Shifts;

use App\Models\GameSession;
use App\Models\OrderItem;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ShiftLedger
{
    /**
     * Open a new cashier shift with an opening cash float in Libyan Dinar millimes.
     */
    public function openShift(User $user, int $openingFloatMillimes, ?string $notes = null): Shift
    {
        // Close any dangling open shifts for safety
        Shift::where('status', 'open')->update([
            'status' => 'closed',
            'ended_at' => now(),
        ]);

        return Shift::create([
            'user_id' => $user->id,
            'started_at' => now(),
            'opening_float_millimes' => $openingFloatMillimes,
            'status' => 'open',
            'notes' => $notes,
        ]);
    }

    /**
     * Close an active shift with a blind drop cash count and calculate cash discrepancy.
     */
    public function closeShift(Shift $shift, int $closingCashCountedMillimes, ?string $notes = null): Shift
    {
        return DB::transaction(function () use ($shift, $closingCashCountedMillimes, $notes) {
            // Calculate all cash payments collected during this shift
            $sessionCash = (int) GameSession::where('shift_id', $shift->id)
                ->where('payment_status', 'paid')
                ->where('payment_method', 'cash')
                ->sum('final_total_millimes');

            // Retail walk-in cash (if standalone)
            $standaloneRetailCash = (int) OrderItem::where('shift_id', $shift->id)
                ->whereNull('game_session_id')
                ->sum('subtotal_millimes');

            $totalCashCollected = $sessionCash + $standaloneRetailCash;
            $expectedCash = $shift->opening_float_millimes + $totalCashCollected;
            $difference = $closingCashCountedMillimes - $expectedCash;

            $shift->update([
                'ended_at' => now(),
                'closing_cash_counted_millimes' => $closingCashCountedMillimes,
                'expected_cash_millimes' => $expectedCash,
                'cash_difference_millimes' => $difference,
                'status' => 'closed',
                'notes' => $notes ?? $shift->notes,
            ]);

            return $shift;
        });
    }

    /**
     * Get summary metrics for the active shift.
     */
    public function getShiftSummary(Shift $shift): array
    {
        $sessionCash = (int) GameSession::where('shift_id', $shift->id)
            ->where('payment_status', 'paid')
            ->where('payment_method', 'cash')
            ->sum('final_total_millimes');

        $sessionCard = (int) GameSession::where('shift_id', $shift->id)
            ->where('payment_status', 'paid')
            ->where('payment_method', 'card')
            ->sum('final_total_millimes');

        $totalRevenue = (int) GameSession::where('shift_id', $shift->id)
            ->where('payment_status', 'paid')
            ->sum('final_total_millimes');

        $retailTotal = (int) OrderItem::where('shift_id', $shift->id)->sum('subtotal_millimes');
        $completedSessionsCount = GameSession::where('shift_id', $shift->id)->where('status', 'completed')->count();

        $expectedCurrentCash = $shift->opening_float_millimes + $sessionCash;

        return [
            'shift_id' => $shift->id,
            'cashier_name' => $shift->user?->name ?? 'Cashier',
            'started_at' => $shift->started_at->toIso8601String(),
            'opening_float_millimes' => $shift->opening_float_millimes,
            'opening_float_lyd' => $shift->opening_float_millimes / 1000,
            'cash_collected_millimes' => $sessionCash,
            'cash_collected_lyd' => $sessionCash / 1000,
            'card_collected_millimes' => $sessionCard,
            'card_collected_lyd' => $sessionCard / 1000,
            'expected_current_cash_millimes' => $expectedCurrentCash,
            'expected_current_cash_lyd' => $expectedCurrentCash / 1000,
            'total_revenue_millimes' => $totalRevenue,
            'total_revenue_lyd' => $totalRevenue / 1000,
            'retail_total_millimes' => $retailTotal,
            'retail_total_lyd' => $retailTotal / 1000,
            'completed_sessions_count' => $completedSessionsCount,
        ];
    }
}
