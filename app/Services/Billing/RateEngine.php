<?php

namespace App\Services\Billing;

use App\Models\GameSession;
use App\Models\PricingRule;

class RateEngine
{
    /**
     * Calculate billable amount for a single interval.
     */
    public function calculateIntervalSubtotal(
        int $durationSeconds,
        int $hourlyRateMillimes,
        float $stationMultiplier = 1.00
    ): array {
        if ($durationSeconds <= 0) {
            return [
                'duration_seconds' => 0,
                'billable_minutes' => 0,
                'subtotal_millimes' => 0,
            ];
        }

        $billableMinutes = (int) ceil($durationSeconds / 60);
        $hourlyRateEffective = $hourlyRateMillimes * $stationMultiplier;
        $subtotalMillimes = (int) round(($billableMinutes / 60) * $hourlyRateEffective);

        return [
            'duration_seconds' => $durationSeconds,
            'billable_minutes' => $billableMinutes,
            'subtotal_millimes' => $subtotalMillimes,
        ];
    }

    /**
     * Compute real-time or final session totals for a given session.
     */
    public function calculateSessionTotal(GameSession $session, ?\DateTimeInterface $targetNow = null): array
    {
        $now = $targetNow ?? now();
        $rule = PricingRule::current();
        $intervals = $session->intervals()->with('pricingTier')->get();

        $totalDurationSeconds = 0;
        $intervalsData = [];
        $calculatedTimeSubtotalMillimes = 0;

        foreach ($intervals as $interval) {
            $isOngoing = is_null($interval->ended_at);
            $endTime = $isOngoing ? $now : $interval->ended_at;

            // If session is paused and currently on this interval, don't count paused time
            $durationSeconds = max(0, $interval->started_at->diffInSeconds($endTime));
            if ($isOngoing && $session->isPaused() && $session->paused_at) {
                $durationSeconds = max(0, $interval->started_at->diffInSeconds($session->paused_at));
            }

            $calc = $this->calculateIntervalSubtotal(
                $durationSeconds,
                $interval->rate_per_hour_millimes,
                (float) $interval->station_multiplier
            );

            $totalDurationSeconds += $calc['duration_seconds'];
            $calculatedTimeSubtotalMillimes += $calc['subtotal_millimes'];

            $intervalsData[] = [
                'id' => $interval->id,
                'pricing_tier' => $interval->pricingTier?->name ?? 'Tier',
                'rate_per_hour_millimes' => $interval->rate_per_hour_millimes,
                'station_multiplier' => $interval->station_multiplier,
                'started_at' => $interval->started_at->toIso8601String(),
                'ended_at' => $interval->ended_at?->toIso8601String(),
                'duration_seconds' => $calc['duration_seconds'],
                'billable_minutes' => $calc['billable_minutes'],
                'subtotal_millimes' => $calc['subtotal_millimes'],
                'is_ongoing' => $isOngoing,
            ];
        }

        $totalMinutes = (int) ceil($totalDurationSeconds / 60);

        // Apply Grace Period Rule
        if ($totalMinutes <= $rule->grace_period_minutes && ($session->isCompleted() || $session->status === 'cancelled')) {
            $finalTimeAmountMillimes = 0;
        } else {
            $finalTimeAmountMillimes = $calculatedTimeSubtotalMillimes;

            // Apply Minimum Charge for Postpaid if beyond grace period
            if ($session->isPostpaid() && $totalMinutes > $rule->grace_period_minutes) {
                $firstTier = $intervals->first()?->pricingTier;
                $baseRate = $firstTier ? $firstTier->hourly_rate_millimes : 6000;
                $multiplier = $session->station ? ($session->station->isVip() ? $rule->vip_multiplier : 1.00) : 1.00;
                $minimumCharge = (int) round(($rule->minimum_charge_minutes / 60) * $baseRate * $multiplier);

                $finalTimeAmountMillimes = max($minimumCharge, $finalTimeAmountMillimes);
            }
        }

        // Retail Add-ons Total
        $retailSubtotalMillimes = (int) $session->orderItems()->sum('subtotal_millimes');

        // Discount
        $discountMillimes = $session->discount_amount_millimes ?? 0;

        // Final Total
        $finalTotalMillimes = max(0, $finalTimeAmountMillimes + $retailSubtotalMillimes - $discountMillimes);

        return [
            'total_duration_seconds' => $totalDurationSeconds,
            'total_billable_minutes' => $totalMinutes,
            'time_amount_millimes' => $finalTimeAmountMillimes,
            'time_amount_lyd' => $finalTimeAmountMillimes / 1000,
            'retail_amount_millimes' => $retailSubtotalMillimes,
            'retail_amount_lyd' => $retailSubtotalMillimes / 1000,
            'discount_amount_millimes' => $discountMillimes,
            'discount_amount_lyd' => $discountMillimes / 1000,
            'final_total_millimes' => $finalTotalMillimes,
            'final_total_lyd' => $finalTotalMillimes / 1000,
            'intervals' => $intervalsData,
        ];
    }
}
