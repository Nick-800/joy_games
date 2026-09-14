<?php

namespace App\Http\Controllers;

use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public const POPULAR_GAMES = [
        'EA Sports FC 25',
        'eFootball 2025',
        'Tekken 8',
        'Mortal Kombat 1',
        'Call of Duty: Modern Warfare III',
        'Grand Theft Auto V',
        'NBA 2K25',
        'Marvel\'s Spider-Man 2',
        'God of War Ragnarök',
        'Gran Turismo 7',
        'Rocket League',
        'Fortnite',
        'It Takes Two',
        'Street Fighter 6',
        'UFC 5',
    ];

    public function index(Request $request): Response
    {
        $stations = Station::orderBy('station_number')
            ->get()
            ->map(fn (Station $station) => [
                'id' => $station->id,
                'name' => $station->name,
                'station_number' => $station->station_number,
                'type' => $station->type,
                'is_vip' => $station->isVip(),
                'default_hourly_rate_millimes' => $station->default_hourly_rate_millimes ?? 10000,
                'default_hourly_rate_lyd' => $station->default_hourly_rate_lyd,
                'hourly_rate_1_2_lyd' => $station->hourly_rate_1_2_lyd ?? $station->default_hourly_rate_lyd,
                'hourly_rate_3_4_lyd' => $station->hourly_rate_3_4_lyd,
                'available_games' => $station->available_games ?? [],
                'is_active' => $station->is_active,
                'current_state' => $station->current_state,
                'has_active_session' => $station->activeSession()->exists(),
            ]);

        $pricingRule = PricingRule::current();

        $pricingTiers = PricingTier::where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (PricingTier $tier) => [
                'id' => $tier->id,
                'name' => $tier->name,
                'controller_count_min' => $tier->controller_count_min,
                'controller_count_max' => $tier->controller_count_max,
                'hourly_rate_millimes' => $tier->hourly_rate_millimes,
                'hourly_rate_lyd' => (int) round($tier->hourly_rate_millimes / 1000),
            ]);

        return Inertia::render('Settings', [
            'stations' => $stations,
            'popularGames' => self::POPULAR_GAMES,
            'pricingRule' => [
                'tv_control_enabled' => (bool) $pricingRule->tv_control_enabled,
                'currency_symbol' => $pricingRule->currency_symbol,
                'currency_code' => $pricingRule->currency_code,
                'vip_multiplier' => $pricingRule->vip_multiplier,
                'grace_period_minutes' => $pricingRule->grace_period_minutes,
                'minimum_charge_minutes' => $pricingRule->minimum_charge_minutes,
            ],
            'pricingTiers' => $pricingTiers,
        ]);
    }

    public function storeStation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'station_number' => ['required', 'integer', 'min:1', 'unique:stations,station_number'],
            'type' => ['required', 'in:standard,vip'],
            'hourly_rate_1_2_lyd' => [
                'nullable',
                'integer',
                'min:5',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && $value % 5 !== 0) {
                        $fail('The 1–2 Players price must be a multiple of 5 LYD.');
                    }
                },
            ],
            'hourly_rate_3_4_lyd' => [
                'nullable',
                'integer',
                'min:5',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && $value % 5 !== 0) {
                        $fail('The 3–4 Players price must be a multiple of 5 LYD.');
                    }
                },
            ],
            'default_hourly_rate_lyd' => [
                'nullable',
                'integer',
                'min:5',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && $value % 5 !== 0) {
                        $fail('The default price must be a multiple of 5 LYD.');
                    }
                },
            ],
            'available_games' => ['nullable', 'array'],
            'available_games.*' => ['string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $rate12 = $validated['hourly_rate_1_2_lyd'] ?? $validated['default_hourly_rate_lyd'] ?? 10;
        $rate34 = $validated['hourly_rate_3_4_lyd'] ?? null;
        $defaultRate = $validated['default_hourly_rate_lyd'] ?? $rate12;

        Station::create([
            'name' => $validated['name'],
            'station_number' => $validated['station_number'],
            'type' => $validated['type'],
            'hourly_rate_1_2_millimes' => $rate12 ? $rate12 * 1000 : null,
            'hourly_rate_3_4_millimes' => $rate34 ? $rate34 * 1000 : null,
            'default_hourly_rate_millimes' => $defaultRate ? $defaultRate * 1000 : null,
            'available_games' => $validated['available_games'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
            'tv_physical_state' => 'standby',
            'current_state' => 'available',
            'tv_os_type' => 'simulated',
        ]);

        return back()->with('success', "Station #{$validated['station_number']} ({$validated['name']}) created successfully.");
    }

    public function updateStation(Request $request, Station $station): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'station_number' => ['required', 'integer', 'min:1', "unique:stations,station_number,{$station->id}"],
            'type' => ['required', 'in:standard,vip'],
            'hourly_rate_1_2_lyd' => [
                'nullable',
                'integer',
                'min:5',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && $value % 5 !== 0) {
                        $fail('The 1–2 Players price must be a multiple of 5 LYD.');
                    }
                },
            ],
            'hourly_rate_3_4_lyd' => [
                'nullable',
                'integer',
                'min:5',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && $value % 5 !== 0) {
                        $fail('The 3–4 Players price must be a multiple of 5 LYD.');
                    }
                },
            ],
            'default_hourly_rate_lyd' => [
                'nullable',
                'integer',
                'min:5',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value && $value % 5 !== 0) {
                        $fail('The default price must be a multiple of 5 LYD.');
                    }
                },
            ],
            'available_games' => ['nullable', 'array'],
            'available_games.*' => ['string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $rate12 = $validated['hourly_rate_1_2_lyd'] ?? $validated['default_hourly_rate_lyd'] ?? $station->hourly_rate_1_2_lyd;
        $rate34 = array_key_exists('hourly_rate_3_4_lyd', $validated) ? $validated['hourly_rate_3_4_lyd'] : $station->hourly_rate_3_4_lyd;
        $defaultRate = $validated['default_hourly_rate_lyd'] ?? $rate12;

        $station->update([
            'name' => $validated['name'],
            'station_number' => $validated['station_number'],
            'type' => $validated['type'],
            'hourly_rate_1_2_millimes' => $rate12 ? $rate12 * 1000 : null,
            'hourly_rate_3_4_millimes' => $rate34 ? $rate34 * 1000 : null,
            'default_hourly_rate_millimes' => $defaultRate ? $defaultRate * 1000 : null,
            'available_games' => $validated['available_games'] ?? [],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', "Station {$station->name} updated successfully.");
    }

    public function destroyStation(Station $station): RedirectResponse
    {
        if ($station->activeSession()->exists() || in_array($station->current_state, ['active_prepaid', 'active_postpaid', 'paused', 'payment_pending'])) {
            return back()->with('error', "Cannot delete {$station->name} while an active gaming session or pending payment exists.");
        }

        $name = $station->name;
        $station->delete();

        return back()->with('success', "Station {$name} deleted successfully.");
    }

    public function updateFeatureFlags(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tv_control_enabled' => ['required', 'boolean'],
        ]);

        $rule = PricingRule::current();
        $rule->update([
            'tv_control_enabled' => $validated['tv_control_enabled'],
        ]);

        $state = $validated['tv_control_enabled'] ? 'enabled' : 'disabled';

        return back()->with('success', "TV controlling system {$state}.");
    }

    public function updatePricingTier(Request $request, PricingTier $tier): RedirectResponse
    {
        $validated = $request->validate([
            'hourly_rate_lyd' => [
                'required',
                'integer',
                'min:5',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value % 5 !== 0) {
                        $fail('The price must be a multiple of 5 LYD.');
                    }
                },
            ],
        ]);

        $tier->update([
            'hourly_rate_millimes' => $validated['hourly_rate_lyd'] * 1000,
        ]);

        return back()->with('success', "Tier {$tier->name} rate updated to {$validated['hourly_rate_lyd']} LYD/hr.");
    }
}
