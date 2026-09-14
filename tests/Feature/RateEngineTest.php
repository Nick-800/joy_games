<?php

use App\Models\GameSession;
use App\Models\GameSessionInterval;
use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use App\Models\User;
use App\Services\Billing\RateEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->rateEngine = app(RateEngine::class);

    $this->rule = PricingRule::create([
        'grace_period_minutes' => 3,
        'minimum_charge_minutes' => 15,
        'rounding_step_minutes' => 5,
        'vip_multiplier' => 1.50,
        'rogue_auto_sleep_seconds' => 180,
        'currency_code' => 'LYD',
        'currency_symbol' => 'د.ل',
        'allow_overtime_default' => true,
    ]);

    $this->tier1 = PricingTier::create([
        'name' => '1–2 Players',
        'controller_count_min' => 1,
        'controller_count_max' => 2,
        'hourly_rate_millimes' => 6000, // 6.000 LYD
    ]);

    $this->tier2 = PricingTier::create([
        'name' => '3–4 Players',
        'controller_count_min' => 3,
        'controller_count_max' => 4,
        'hourly_rate_millimes' => 10000, // 10.000 LYD
    ]);

    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->station = Station::create([
        'name' => 'PS5 Station 01',
        'station_number' => 1,
        'type' => 'standard',
    ]);
});

test('it calculates exact interval subtotal in millimes', function () {
    // 30 minutes at 6.000 LYD/hr = 3.000 LYD (3000 millimes)
    $calc = $this->rateEngine->calculateIntervalSubtotal(1800, 6000, 1.00);

    expect($calc['billable_minutes'])->toBe(30)
        ->and($calc['subtotal_millimes'])->toBe(3000);
});

test('it computes sliced interval billing across multiple controller tiers', function () {
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'active',
        'started_at' => now()->subMinutes(90),
    ]);

    // Interval 1: 30 minutes with Tier 1 (6.000 LYD/hr) -> 3.000 LYD
    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier1->id,
        'started_at' => now()->subMinutes(90),
        'ended_at' => now()->subMinutes(60),
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
    ]);

    // Interval 2: 60 minutes with Tier 2 (10.000 LYD/hr) -> 10.000 LYD
    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier2->id,
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'rate_per_hour_millimes' => 10000,
        'station_multiplier' => 1.00,
    ]);

    $total = $this->rateEngine->calculateSessionTotal($session);

    // 13.000 LYD rounds UP to closest multiple of 5 LYD = 15.000 LYD
    expect($total['time_amount_millimes'])->toBe(15000)
        ->and($total['final_total_lyd'])->toBe(15);
});

test('it waives time fee if session ended within 3-minute grace period', function () {
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => now()->subMinutes(2),
        'ended_at' => now(),
    ]);

    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier1->id,
        'started_at' => now()->subMinutes(2),
        'ended_at' => now(),
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
    ]);

    $total = $this->rateEngine->calculateSessionTotal($session);

    expect($total['time_amount_millimes'])->toBe(0);
});

test('it enforces 15-minute minimum charge for postpaid sessions beyond grace period rounded up to 5 LYD', function () {
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => now()->subMinutes(6),
        'ended_at' => now(),
    ]);

    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier1->id,
        'started_at' => now()->subMinutes(6),
        'ended_at' => now(),
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
    ]);

    $total = $this->rateEngine->calculateSessionTotal($session);

    // 15 minutes minimum of 6.000 LYD/hr = 1.500 LYD raw -> rounded UP to closest 5 LYD = 5.000 LYD
    expect($total['time_amount_millimes'])->toBe(5000);
});

test('it rounds calculated bill up to the closest multiple of 5 LYD (ceiling, no fractions)', function () {
    // Session of 10 minutes at 6.000 LYD/hr = 1.000 LYD -> rounded UP to 5.000 LYD
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => now()->subMinutes(10),
        'ended_at' => now(),
    ]);

    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier1->id,
        'started_at' => now()->subMinutes(10),
        'ended_at' => now(),
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
    ]);

    $total = $this->rateEngine->calculateSessionTotal($session);

    expect($total['time_amount_millimes'])->toBe(5000)
        ->and($total['final_total_lyd'])->toBe(5);
});
