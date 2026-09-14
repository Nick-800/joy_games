<?php

use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use App\Models\User;
use App\Services\Billing\RateEngine;
use App\Services\Sessions\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->rateEngine = app(RateEngine::class);
    $this->sessionManager = app(SessionManager::class);

    $this->rule = PricingRule::create([
        'grace_period_minutes' => 3,
        'minimum_charge_minutes' => 15,
        'rounding_step_minutes' => 5,
        'vip_multiplier' => 1.50,
        'rogue_auto_sleep_seconds' => 120,
        'currency_code' => 'LYD',
        'currency_symbol' => 'د.ل',
        'allow_overtime_default' => true,
    ]);

    $this->tier1 = PricingTier::create([
        'name' => '1–2 Players',
        'controller_count_min' => 1,
        'controller_count_max' => 2,
        'hourly_rate_millimes' => 6000,
    ]);

    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->station = Station::create([
        'name' => 'PS5 Station 01',
        'station_number' => 1,
        'type' => 'standard',
        'tv_os_type' => 'simulated',
    ]);
});

test('a paused session with total_paused_seconds excludes paused minutes from billing', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier,
    );

    $session->update([
        'started_at' => now()->subMinutes(60),
        'total_paused_seconds' => 600, // 10 minutes of pause
    ]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $total = $this->rateEngine->calculateSessionTotal($session);

    // 60 min elapsed - 10 min paused = 50 billable minutes * (6000 / 60) = 5000 millimes
    expect($total['time_amount_millimes'])->toBe(5000);
});

test('endSession bakes the pause subtraction into the closed interval', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier,
    );

    $session->update([
        'started_at' => now()->subMinutes(60),
        'total_paused_seconds' => 600,
    ]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $this->sessionManager->endSession($session);

    expect($session->fresh()->intervals()->first()->billable_minutes)->toBe(50)
        ->and($session->fresh()->final_total_millimes)->toBe(5000);
});

test('switching tiers bills each slice independently of total_paused_seconds', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier,
    );

    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);
    $session->update(['total_paused_seconds' => 600]);

    $tier2 = PricingTier::create([
        'name' => '3–4 Players',
        'controller_count_min' => 3,
        'controller_count_max' => 4,
        'hourly_rate_millimes' => 10000,
    ]);

    $this->sessionManager->switchTier($session, $tier2);

    // First slice: 60 min elapsed - 600s paused = 50 billable min @ 6 LYD/hr = 5000
    expect($session->intervals()->first()->billable_minutes)->toBe(50)
        ->and($session->intervals()->first()->subtotal_millimes)->toBe(5000)
        ->and($session->intervals()->count())->toBe(2);
});

test('multiple pauses correctly accumulated into total_paused_seconds', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier,
    );

    $session->update([
        'started_at' => now()->subMinutes(70),
        'total_paused_seconds' => 1500, // 25 minutes total paused
    ]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(70)]);

    $total = $this->rateEngine->calculateSessionTotal($session);

    // 70 elapsed - 25 paused = 45 billable min @ 6 LYD/hr = 4500 millimes
    expect($total['time_amount_millimes'])->toBe(4500);
});

test('currently-paused session caps duration at paused_at', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier,
    );

    // Interval started 60 min ago, paused 15 min ago (so 45 min active, 15 min paused-in-progress)
    $session->update([
        'started_at' => now()->subMinutes(60),
        'status' => 'paused',
        'paused_at' => now()->subMinutes(15),
    ]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $total = $this->rateEngine->calculateSessionTotal($session);

    // duration is capped at paused_at = 45 minutes; paused seconds 0 because pause-in-progress
    expect($total['time_amount_millimes'])->toBe(4500);
});

test('completed session with multiple pauses totals correctly after endSession + settlePayment', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier,
    );

    $session->update([
        'started_at' => now()->subMinutes(60),
        'total_paused_seconds' => 600,
    ]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $this->sessionManager->endSession($session);

    // Pay exactly 5000 millimes + 0 change
    $this->sessionManager->settlePayment($session, 'cash', 5000);

    expect($session->fresh()->final_total_millimes)->toBe(5000)
        ->and($session->fresh()->cash_change_millimes)->toBe(0)
        ->and($session->fresh()->payment_status)->toBe('paid');
});

test('settlePayment rejects underpayment when no discount given', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier,
    );

    $session->update(['started_at' => now()->subMinutes(60)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $this->sessionManager->endSession($session);

    expect(fn () => $this->sessionManager->settlePayment($session, 'cash', 1000))
        ->toThrow(InvalidArgumentException::class);
});

test('settlePayment allows underpayment when discount covers the gap', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier,
    );

    $session->update(['started_at' => now()->subMinutes(60)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $this->sessionManager->endSession($session);

    // Total is 6000 millimes, discount 5000, cash received 1000 -> final = 1000
    $this->sessionManager->settlePayment($session, 'cash', 1000, 5000);

    expect($session->fresh()->final_total_millimes)->toBe(1000)
        ->and($session->fresh()->discount_amount_millimes)->toBe(5000);
});
