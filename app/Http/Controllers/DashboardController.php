<?php

namespace App\Http\Controllers;

use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Station;
use App\Models\User;
use App\Services\Billing\RateEngine;
use App\Services\Shifts\ShiftLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected RateEngine $rateEngine,
        protected ShiftLedger $shiftLedger
    ) {}

    public function index(Request $request): Response
    {
        $stations = Station::where('is_active', true)
            ->with([
                'activeSession' => function ($query) {
                    $query->with(['intervals.pricingTier', 'orderItems.product', 'cashier']);
                },
            ])
            ->orderBy('station_number')
            ->get()
            ->map(function (Station $station) {
                $session = $station->activeSession;
                $sessionData = null;

                if ($session) {
                    $calc = $this->rateEngine->calculateSessionTotal($session);
                    $activeInterval = $session->activeInterval;

                    $sessionData = [
                        'id' => $session->id,
                        'session_type' => $session->session_type,
                        'status' => $session->status,
                        'customer_name' => $session->customer_name,
                        'customer_phone' => $session->customer_phone,
                        'allocated_minutes' => $session->allocated_minutes,
                        'allow_overtime' => $session->allow_overtime,
                        'started_at' => $session->started_at->toIso8601String(),
                        'paused_at' => $session->paused_at?->toIso8601String(),
                        'total_paused_seconds' => $session->total_paused_seconds,
                        'pause_reason' => $session->pause_reason,
                        'ended_at' => $session->ended_at?->toIso8601String(),
                        'current_tier' => $activeInterval ? [
                            'id' => $activeInterval->pricingTier?->id,
                            'name' => $activeInterval->pricingTier?->name,
                            'rate_per_hour_millimes' => $activeInterval->rate_per_hour_millimes,
                            'rate_per_hour_lyd' => $activeInterval->rate_per_hour_millimes / 1000,
                        ] : null,
                        'time_amount_millimes' => $calc['time_amount_millimes'],
                        'time_amount_lyd' => $calc['time_amount_lyd'],
                        'retail_amount_millimes' => $calc['retail_amount_millimes'],
                        'retail_amount_lyd' => $calc['retail_amount_lyd'],
                        'discount_amount_millimes' => $calc['discount_amount_millimes'],
                        'discount_amount_lyd' => $calc['discount_amount_lyd'],
                        'final_total_millimes' => $calc['final_total_millimes'],
                        'final_total_lyd' => $calc['final_total_lyd'],
                        'total_duration_seconds' => $calc['total_duration_seconds'],
                        'total_billable_minutes' => $calc['total_billable_minutes'],
                        'intervals' => $calc['intervals'],
                        'order_items' => $session->orderItems->map(fn ($item) => [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'item_name' => $item->item_name,
                            'unit_price_millimes' => $item->unit_price_millimes,
                            'unit_price_lyd' => $item->unit_price_millimes / 1000,
                            'quantity' => $item->quantity,
                            'subtotal_millimes' => $item->subtotal_millimes,
                            'subtotal_lyd' => $item->subtotal_millimes / 1000,
                        ]),
                    ];
                }

                $rogueDurationSeconds = 0;
                if ($station->isRogue() && $station->first_detected_on_at) {
                    $rogueDurationSeconds = max(0, $station->first_detected_on_at->diffInSeconds(now()));
                }

                return [
                    'id' => $station->id,
                    'name' => $station->name,
                    'station_number' => $station->station_number,
                    'type' => $station->type,
                    'is_vip' => $station->isVip(),
                    'tv_ip_address' => $station->tv_ip_address,
                    'tv_mac_address' => $station->tv_mac_address,
                    'tv_os_type' => $station->tv_os_type,
                    'tv_physical_state' => $station->tv_physical_state,
                    'current_state' => $station->current_state,
                    'consecutive_on_pings' => $station->consecutive_on_pings,
                    'is_rogue' => $station->isRogue(),
                    'rogue_duration_seconds' => $rogueDurationSeconds,
                    'first_detected_on_at' => $station->first_detected_on_at?->toIso8601String(),
                    'last_ping_at' => $station->last_ping_at?->toIso8601String(),
                    'active_session' => $sessionData,
                ];
            });

        $pricingTiers = PricingTier::where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn ($tier) => [
                'id' => $tier->id,
                'name' => $tier->name,
                'controller_count_min' => $tier->controller_count_min,
                'controller_count_max' => $tier->controller_count_max,
                'hourly_rate_millimes' => $tier->hourly_rate_millimes,
                'hourly_rate_lyd' => $tier->hourly_rate_millimes / 1000,
            ]);

        $pricingRule = PricingRule::current();

        $products = Product::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category,
                'price_millimes' => $p->price_millimes,
                'price_lyd' => $p->price_millimes / 1000,
                'stock_quantity' => $p->stock_quantity,
            ]);

        $activeShift = Shift::active();
        $shiftSummary = $activeShift ? $this->shiftLedger->getShiftSummary($activeShift) : null;

        $staffUsers = User::where('is_active', true)
            ->get(['id', 'name', 'email', 'role']);

        return Inertia::render('Dashboard', [
            'stations' => $stations,
            'pricingTiers' => $pricingTiers,
            'pricingRule' => [
                'grace_period_minutes' => $pricingRule->grace_period_minutes,
                'minimum_charge_minutes' => $pricingRule->minimum_charge_minutes,
                'rounding_step_minutes' => $pricingRule->rounding_step_minutes,
                'vip_multiplier' => $pricingRule->vip_multiplier,
                'currency_code' => $pricingRule->currency_code,
                'currency_symbol' => $pricingRule->currency_symbol,
            ],
            'products' => $products,
            'activeShift' => $shiftSummary,
            'staffUsers' => $staffUsers,
            'serverTime' => now()->toIso8601String(),
        ]);
    }
}
