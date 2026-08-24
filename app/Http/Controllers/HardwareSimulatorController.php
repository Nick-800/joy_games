<?php

namespace App\Http\Controllers;

use App\Models\Station;
use App\Services\Tv\TvReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HardwareSimulatorController extends Controller
{
    public function __construct(
        protected TvReconciliationService $reconciliationService
    ) {}

    public function simulateWake(Station $station): RedirectResponse
    {
        $station->update([
            'tv_physical_state' => 'screen_on',
            'last_ping_at' => now(),
        ]);

        $this->reconciliationService->reconcileStation($station);

        return back()->with('success', "Simulated Smart TV Screen ON (WoL) on {$station->name}");
    }

    public function simulateSleep(Station $station): RedirectResponse
    {
        $station->update([
            'tv_physical_state' => 'standby',
            'consecutive_on_pings' => 0,
            'first_detected_on_at' => null,
            'last_ping_at' => now(),
        ]);

        $this->reconciliationService->reconcileStation($station);

        return back()->with('success', "Simulated Smart TV Standby (Screen OFF) on {$station->name}");
    }

    public function triggerRogue(Station $station): RedirectResponse
    {
        $station->update([
            'tv_physical_state' => 'screen_on',
            'last_ping_at' => now(),
        ]);

        $this->reconciliationService->reconcileStation($station);

        return back()->with('success', "Simulated Unauthorized TV Remote Turn-On on {$station->name}");
    }

    public function fastForwardTime(Request $request, Station $station): RedirectResponse
    {
        $minutes = (int) $request->input('minutes', 15);
        $session = $station->activeSession;

        if ($session) {
            $newStartedAt = $session->started_at->subMinutes($minutes);
            $session->update(['started_at' => $newStartedAt]);

            // Adjust first interval started_at as well
            $session->intervals()->orderBy('id')->first()?->update(['started_at' => $newStartedAt]);

            $this->reconciliationService->reconcileStation($station);
        }

        return back()->with('success', "Fast-forwarded clock on {$station->name} by {$minutes} minutes");
    }
}
