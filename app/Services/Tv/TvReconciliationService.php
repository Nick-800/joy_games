<?php

namespace App\Services\Tv;

use App\Models\PricingRule;
use App\Models\Station;
use App\Models\StationAudit;
use App\Services\Sessions\SessionManager;
use Illuminate\Support\Facades\Log;

class TvReconciliationService
{
    public function __construct(
        protected SessionManager $sessionManager
    ) {}

    /**
     * Run full state reconciliation across all active stations.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reconcileAll(): array
    {
        $stations = Station::where('is_active', true)->get();
        $results = [];

        foreach ($stations as $station) {
            $results[$station->id] = $this->reconcileStation($station);
        }

        return $results;
    }

    /**
     * Reconcile physical Smart TV power state against database operational state.
     *
     * @return array<string, mixed>
     */
    public function reconcileStation(Station $station): array
    {
        $driver = TvDriverFactory::make($station);
        $status = $driver->pollStatus($station);
        $previousPhysicalState = $station->tv_physical_state;
        $rule = PricingRule::current();
        $autoSleepCutoffSeconds = $rule?->rogue_auto_sleep_seconds ?? 120; // 2 minutes

        $isRogue = false;
        $actionTaken = 'none';

        // Update physical state and last ping
        $station->tv_physical_state = $status->value;
        $station->last_ping_at = now();

        if ($status === TvStatus::SCREEN_ON) {
            if ($station->current_state === 'available') {
                // TV is ON without an active customer session
                $station->consecutive_on_pings++;
                if (! $station->first_detected_on_at) {
                    $station->first_detected_on_at = now();
                }

                // 2-ping debounce for rogue remote press detection
                if ($station->consecutive_on_pings >= 2) {
                    $isRogue = true;

                    // Check if rogue duration exceeds 2-minute auto-cutoff safeguard
                    $unauthorizedSeconds = $station->first_detected_on_at ? abs(now()->diffInSeconds($station->first_detected_on_at)) : 0;
                    if ($unauthorizedSeconds >= $autoSleepCutoffSeconds) {
                        Log::warning("TvReconciliationService: Auto-blackout safeguard triggered for Station #{$station->station_number} after {$unauthorizedSeconds}s rogue power-on.");

                        $driver->turnOff($station);
                        $station->tv_physical_state = 'standby';
                        $station->consecutive_on_pings = 0;
                        $station->first_detected_on_at = null;
                        $actionTaken = 'auto_blackout_enforced';

                        StationAudit::create([
                            'station_id' => $station->id,
                            'event_type' => 'auto_blackout_triggered',
                            'physical_state' => 'screen_on',
                            'expected_state' => 'available',
                            'details' => [
                                'reason' => 'rogue_auto_cutoff_exceeded',
                                'unauthorized_seconds' => $unauthorizedSeconds,
                            ],
                        ]);
                    } else {
                        $actionTaken = 'flagged_rogue';
                    }
                }
            } else {
                // Normal active session
                $station->consecutive_on_pings = 0;
                $station->first_detected_on_at = null;
            }
        } elseif ($status === TvStatus::STANDBY) {
            // TV is in Standby / Off
            $station->consecutive_on_pings = 0;
            $station->first_detected_on_at = null;

            // If station is marked active prepaid or postpaid, customer screen was unexpectedly turned off
            if (in_array($station->current_state, ['active_prepaid', 'active_postpaid'])) {
                $session = $station->activeSession;
                if ($session && $session->status === 'active') {
                    Log::warning("TvReconciliationService: Active session on Station #{$station->station_number} detected TV Standby. Auto-pausing timer.");
                    $this->sessionManager->pauseSession($session, 'unexpected_tv_off');
                    $actionTaken = 'auto_paused_session';

                    StationAudit::create([
                        'station_id' => $station->id,
                        'game_session_id' => $session->id,
                        'event_type' => 'unexpected_tv_off',
                        'physical_state' => 'standby',
                        'expected_state' => $station->current_state,
                        'details' => ['reason' => 'tv_screen_turned_off_during_active_session'],
                    ]);
                }
            }
        }

        $station->save();

        return [
            'station_id' => $station->id,
            'station_name' => $station->name,
            'physical_state' => $station->tv_physical_state,
            'current_state' => $station->current_state,
            'is_rogue' => $isRogue,
            'consecutive_on_pings' => $station->consecutive_on_pings,
            'action_taken' => $actionTaken,
        ];
    }
}
