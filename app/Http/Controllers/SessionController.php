<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\PricingTier;
use App\Models\Station;
use App\Services\Sessions\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(
        protected SessionManager $sessionManager
    ) {}

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'station_id' => ['required', 'exists:stations,id'],
            'session_type' => ['required', 'in:prepaid,postpaid'],
            'pricing_tier_id' => ['required', 'exists:pricing_tiers,id'],
            'allocated_minutes' => ['nullable', 'required_if:session_type,prepaid', 'integer', 'min:5'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'auto_wake' => ['boolean'],
            'allow_overtime' => ['boolean'],
            'backdate_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $station = Station::findOrFail($validated['station_id']);
        $tier = PricingTier::findOrFail($validated['pricing_tier_id']);
        $cashier = $request->user();

        $session = $this->sessionManager->startSession(
            station: $station,
            sessionType: $validated['session_type'],
            tier: $tier,
            cashier: $cashier,
            allocatedMinutes: $validated['allocated_minutes'] ?? null,
            customerName: $validated['customer_name'] ?? null,
            customerPhone: $validated['customer_phone'] ?? null,
            autoWake: $validated['auto_wake'] ?? true,
            allowOvertime: $validated['allow_overtime'] ?? true
        );

        if (! empty($validated['backdate_minutes']) && $validated['backdate_minutes'] > 0) {
            $startedAt = now()->subMinutes($validated['backdate_minutes']);
            $session->update(['started_at' => $startedAt]);
            $session->intervals()->first()?->update(['started_at' => $startedAt]);
        }

        return back()->with('success', "Session started on {$station->name}");
    }

    public function switchTier(Request $request, GameSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'pricing_tier_id' => ['required', 'exists:pricing_tiers,id'],
        ]);

        $tier = PricingTier::findOrFail($validated['pricing_tier_id']);
        $this->sessionManager->switchTier($session, $tier);

        return back()->with('success', "Switched to {$tier->name}");
    }

    public function extendTime(Request $request, GameSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:1'],
        ]);

        $this->sessionManager->extendPrepaidTime($session, (int) $validated['minutes']);

        return back()->with('success', "Extended session by {$validated['minutes']} minutes");
    }

    public function pause(Request $request, GameSession $session): RedirectResponse
    {
        $reason = $request->input('reason', 'Customer Break');
        $this->sessionManager->pauseSession($session, $reason);

        return back()->with('success', "Session on {$session->station->name} paused");
    }

    public function resume(Request $request, GameSession $session): RedirectResponse
    {
        $this->sessionManager->resumeSession($session);

        return back()->with('success', "Session on {$session->station->name} resumed");
    }

    public function end(Request $request, GameSession $session): RedirectResponse
    {
        $this->sessionManager->endSession($session);

        return back()->with('success', "Session on {$session->station->name} ended. Ready for checkout.");
    }

    public function settle(Request $request, GameSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:cash,card,split'],
            'cash_received_millimes' => ['nullable', 'integer', 'min:0'],
            'discount_millimes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->sessionManager->settlePayment(
            session: $session,
            paymentMethod: $validated['payment_method'],
            cashReceivedMillimes: $validated['cash_received_millimes'] ?? 0,
            discountMillimes: $validated['discount_millimes'] ?? 0,
            notes: $validated['notes'] ?? null
        );

        return back()->with('success', "Payment settled for {$session->station->name}");
    }

    public function transfer(Request $request, GameSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'destination_station_id' => ['required', 'exists:stations,id'],
        ]);

        $destination = Station::findOrFail($validated['destination_station_id']);
        $this->sessionManager->transferStation($session, $destination);

        return back()->with('success', "Transferred session to {$destination->name}");
    }
}
