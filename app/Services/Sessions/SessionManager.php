<?php

namespace App\Services\Sessions;

use App\Models\GameSession;
use App\Models\GameSessionInterval;
use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Shift;
use App\Models\Station;
use App\Models\StationAudit;
use App\Models\User;
use App\Services\Billing\RateEngine;
use App\Services\Tv\TvDriverFactory;
use Exception;
use Illuminate\Support\Facades\DB;

class SessionManager
{
    public function __construct(
        protected RateEngine $rateEngine
    ) {}

    /**
     * Start a new prepaid countdown or postpaid open-ended tab session.
     */
    public function startSession(
        Station $station,
        string $sessionType,
        PricingTier $tier,
        User $cashier,
        ?int $allocatedMinutes = null,
        ?string $customerName = null,
        ?string $customerPhone = null,
        bool $autoWake = true,
        bool $allowOvertime = true
    ): GameSession {
        return DB::transaction(function () use (
            $station,
            $sessionType,
            $tier,
            $cashier,
            $allocatedMinutes,
            $customerName,
            $customerPhone,
            $autoWake,
            $allowOvertime
        ) {
            $rule = PricingRule::current();
            $shift = Shift::active();

            $now = now();
            $state = $sessionType === 'prepaid' ? 'active_prepaid' : 'active_postpaid';

            $session = GameSession::create([
                'station_id' => $station->id,
                'shift_id' => $shift?->id,
                'cashier_id' => $cashier->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'session_type' => $sessionType,
                'status' => 'active',
                'allocated_minutes' => $sessionType === 'prepaid' ? $allocatedMinutes : null,
                'allow_overtime' => $allowOvertime,
                'started_at' => $now,
            ]);

            $stationMultiplier = $station->isVip() ? (float) $rule->vip_multiplier : 1.00;

            GameSessionInterval::create([
                'game_session_id' => $session->id,
                'pricing_tier_id' => $tier->id,
                'started_at' => $now,
                'rate_per_hour_millimes' => $tier->hourly_rate_millimes,
                'station_multiplier' => $stationMultiplier,
            ]);

            $station->update([
                'current_state' => $state,
                'consecutive_on_pings' => 0,
                'first_detected_on_at' => null,
            ]);

            if ($autoWake) {
                TvDriverFactory::make($station)->turnOn($station);
            }

            StationAudit::create([
                'station_id' => $station->id,
                'game_session_id' => $session->id,
                'user_id' => $cashier->id,
                'event_type' => 'tv_wake_sent',
                'physical_state' => $station->tv_physical_state,
                'expected_state' => $state,
                'details' => [
                    'session_type' => $sessionType,
                    'tier' => $tier->name,
                    'allocated_minutes' => $allocatedMinutes,
                ],
            ]);

            return $session;
        });
    }

    /**
     * Switch active player controller tier mid-session (sliced interval transition).
     */
    public function switchTier(GameSession $session, PricingTier $newTier): GameSessionInterval
    {
        return DB::transaction(function () use ($session, $newTier) {
            $now = now();
            $activeInterval = $session->activeInterval;

            if ($activeInterval) {
                $duration = max(0, $activeInterval->started_at->diffInSeconds($now));
                $calc = $this->rateEngine->calculateIntervalSubtotal(
                    $duration,
                    $activeInterval->rate_per_hour_millimes,
                    (float) $activeInterval->station_multiplier
                );

                $activeInterval->update([
                    'ended_at' => $now,
                    'duration_seconds' => $calc['duration_seconds'],
                    'billable_minutes' => $calc['billable_minutes'],
                    'subtotal_millimes' => $calc['subtotal_millimes'],
                ]);
            }

            $rule = PricingRule::current();
            $stationMultiplier = $session->station?->isVip() ? (float) $rule->vip_multiplier : 1.00;

            $newInterval = GameSessionInterval::create([
                'game_session_id' => $session->id,
                'pricing_tier_id' => $newTier->id,
                'started_at' => $now,
                'rate_per_hour_millimes' => $newTier->hourly_rate_millimes,
                'station_multiplier' => $stationMultiplier,
            ]);

            StationAudit::create([
                'station_id' => $session->station_id,
                'game_session_id' => $session->id,
                'user_id' => auth()->id() ?? $session->cashier_id,
                'event_type' => 'tier_switched',
                'physical_state' => $session->station->tv_physical_state,
                'expected_state' => $session->station->current_state,
                'details' => [
                    'new_tier_id' => $newTier->id,
                    'new_tier_name' => $newTier->name,
                ],
            ]);

            return $newInterval;
        });
    }

    /**
     * Extend time for an active prepaid session.
     */
    public function extendPrepaidTime(GameSession $session, int $additionalMinutes): GameSession
    {
        $session->update([
            'allocated_minutes' => ($session->allocated_minutes ?? 0) + $additionalMinutes,
        ]);

        return $session;
    }

    /**
     * Pause an active session (protects customer from being billed during breaks or disconnects).
     */
    public function pauseSession(GameSession $session, ?string $reason = 'Customer Break'): GameSession
    {
        return DB::transaction(function () use ($session, $reason) {
            $now = now();
            $session->update([
                'status' => 'paused',
                'paused_at' => $now,
                'pause_reason' => $reason,
            ]);

            $session->station->update([
                'current_state' => 'paused',
            ]);

            // Turn off TV screen while paused
            TvDriverFactory::make($session->station)->turnOff($session->station);

            StationAudit::create([
                'station_id' => $session->station_id,
                'game_session_id' => $session->id,
                'user_id' => auth()->id() ?? $session->cashier_id,
                'event_type' => 'manual_tv_off',
                'physical_state' => $session->station->tv_physical_state,
                'expected_state' => 'paused',
                'details' => ['reason' => $reason],
            ]);

            return $session;
        });
    }

    /**
     * Resume a paused session.
     */
    public function resumeSession(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session) {
            $now = now();
            $pausedSeconds = $session->paused_at ? max(0, $session->paused_at->diffInSeconds($now)) : 0;

            $state = $session->isPrepaid() ? 'active_prepaid' : 'active_postpaid';

            $session->update([
                'status' => 'active',
                'paused_at' => null,
                'total_paused_seconds' => $session->total_paused_seconds + $pausedSeconds,
                'pause_reason' => null,
            ]);

            $session->station->update([
                'current_state' => $state,
            ]);

            // Turn TV screen back on via WoL
            TvDriverFactory::make($session->station)->turnOn($session->station);

            return $session;
        });
    }

    /**
     * End active gaming session and transition to payment pending invoice mode.
     */
    public function endSession(GameSession $session): GameSession
    {
        return DB::transaction(function () use ($session) {
            $now = now();

            // Close active interval
            $activeInterval = $session->activeInterval;
            if ($activeInterval) {
                $duration = max(0, $activeInterval->started_at->diffInSeconds($now));
                $calc = $this->rateEngine->calculateIntervalSubtotal(
                    $duration,
                    $activeInterval->rate_per_hour_millimes,
                    (float) $activeInterval->station_multiplier
                );

                $activeInterval->update([
                    'ended_at' => $now,
                    'duration_seconds' => $calc['duration_seconds'],
                    'billable_minutes' => $calc['billable_minutes'],
                    'subtotal_millimes' => $calc['subtotal_millimes'],
                ]);
            }

            // Calculate final financial totals in LYD
            $totals = $this->rateEngine->calculateSessionTotal($session, $now);

            $session->update([
                'status' => 'payment_pending',
                'ended_at' => $now,
                'time_amount_millimes' => $totals['time_amount_millimes'],
                'retail_amount_millimes' => $totals['retail_amount_millimes'],
                'final_total_millimes' => $totals['final_total_millimes'],
            ]);

            $session->station->update([
                'current_state' => 'payment_pending',
            ]);

            // Turn off TV screen
            TvDriverFactory::make($session->station)->turnOff($session->station);

            StationAudit::create([
                'station_id' => $session->station_id,
                'game_session_id' => $session->id,
                'user_id' => auth()->id() ?? $session->cashier_id,
                'event_type' => 'manual_tv_off',
                'physical_state' => $session->station->tv_physical_state,
                'expected_state' => 'payment_pending',
                'details' => $totals,
            ]);

            return $session;
        });
    }

    /**
     * Settle payment (Cash with change calculation / Card / Split) and release station.
     */
    public function settlePayment(
        GameSession $session,
        string $paymentMethod,
        int $cashReceivedMillimes = 0,
        int $discountMillimes = 0,
        ?string $notes = null
    ): GameSession {
        return DB::transaction(function () use (
            $session,
            $paymentMethod,
            $cashReceivedMillimes,
            $discountMillimes,
            $notes
        ) {
            $now = now();
            $totals = $this->rateEngine->calculateSessionTotal($session, $now);

            $finalTotal = max(0, $totals['time_amount_millimes'] + $totals['retail_amount_millimes'] - $discountMillimes);
            $change = $paymentMethod === 'cash' ? max(0, $cashReceivedMillimes - $finalTotal) : 0;

            $session->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => $paymentMethod,
                'discount_amount_millimes' => $discountMillimes,
                'final_total_millimes' => $finalTotal,
                'cash_received_millimes' => $paymentMethod === 'cash' ? $cashReceivedMillimes : null,
                'cash_change_millimes' => $change,
                'notes' => $notes ?? $session->notes,
                'ended_at' => $session->ended_at ?? $now,
            ]);

            $session->station->update([
                'current_state' => 'available',
                'consecutive_on_pings' => 0,
                'first_detected_on_at' => null,
            ]);

            return $session;
        });
    }

    /**
     * Transfer an active session from one station to another.
     */
    public function transferStation(GameSession $session, Station $destinationStation): GameSession
    {
        if (! $destinationStation->isAvailable()) {
            throw new Exception("Destination station {$destinationStation->name} is not available.");
        }

        return DB::transaction(function () use ($session, $destinationStation) {
            $sourceStation = $session->station;

            // Turn off source TV
            TvDriverFactory::make($sourceStation)->turnOff($sourceStation);
            $sourceStation->update(['current_state' => 'available']);

            // Turn on destination TV
            TvDriverFactory::make($destinationStation)->turnOn($destinationStation);
            $destinationStation->update(['current_state' => $session->isPrepaid() ? 'active_prepaid' : 'active_postpaid']);

            $session->update(['station_id' => $destinationStation->id]);

            StationAudit::create([
                'station_id' => $destinationStation->id,
                'game_session_id' => $session->id,
                'user_id' => auth()->id() ?? $session->cashier_id,
                'event_type' => 'station_transfer',
                'physical_state' => $destinationStation->tv_physical_state,
                'expected_state' => $destinationStation->current_state,
                'details' => [
                    'from_station_id' => $sourceStation->id,
                    'to_station_id' => $destinationStation->id,
                ],
            ]);

            return $session;
        });
    }
}
