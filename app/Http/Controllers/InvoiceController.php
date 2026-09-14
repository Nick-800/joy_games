<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Services\Billing\RateEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(protected RateEngine $rateEngine) {}

    public function show(Request $request, GameSession $session): Response
    {
        $this->authorize('view', $session);

        $session->load(['station', 'cashier', 'intervals.pricingTier', 'orderItems.product', 'shift']);

        $totals = $this->rateEngine->calculateSessionTotal($session);

        return Inertia::render('Invoice', [
            'session' => [
                'id' => $session->id,
                'session_type' => $session->session_type,
                'status' => $session->status,
                'customer_name' => $session->customer_name,
                'customer_phone' => $session->customer_phone,
                'started_at' => $session->started_at?->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
                'paused_at' => $session->paused_at?->toIso8601String(),
                'total_paused_seconds' => $session->total_paused_seconds,
                'cashier_name' => $session->cashier?->name,
                'station_name' => $session->station?->name,
                'payment_method' => $session->payment_method,
                'payment_status' => $session->payment_status,
            ],
            'totals' => $totals,
            'intervals' => $session->intervals->map(fn ($i) => [
                'id' => $i->id,
                'pricing_tier' => $i->pricingTier?->name ?? 'Tier',
                'rate_per_hour_millimes' => $i->rate_per_hour_millimes,
                'station_multiplier' => $i->station_multiplier,
                'started_at' => $i->started_at?->toIso8601String(),
                'ended_at' => $i->ended_at?->toIso8601String(),
                'duration_seconds' => $i->duration_seconds,
                'billable_minutes' => $i->billable_minutes,
                'subtotal_millimes' => $i->subtotal_millimes,
            ]),
            'orderItems' => $session->orderItems->map(fn ($o) => [
                'id' => $o->id,
                'item_name' => $o->item_name,
                'quantity' => $o->quantity,
                'unit_price_millimes' => $o->unit_price_millimes,
                'subtotal_millimes' => $o->subtotal_millimes,
            ]),
        ]);
    }
}
