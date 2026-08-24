<?php

use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use App\Models\User;
use App\Services\Sessions\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->sessionManager = app(SessionManager::class);

    PricingRule::create([
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

    $this->tier2 = PricingTier::create([
        'name' => '3–4 Players',
        'controller_count_min' => 3,
        'controller_count_max' => 4,
        'hourly_rate_millimes' => 10000,
    ]);

    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->station1 = Station::create(['name' => 'PS5 Station 01', 'station_number' => 1, 'type' => 'standard', 'current_state' => 'available', 'tv_physical_state' => 'standby', 'tv_os_type' => 'simulated']);
    $this->station2 = Station::create(['name' => 'PS5 Station 02', 'station_number' => 2, 'type' => 'standard', 'current_state' => 'available', 'tv_physical_state' => 'standby', 'tv_os_type' => 'simulated']);
});

test('it starts a new prepaid session and sets station state to active_prepaid and turns TV on', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station1,
        sessionType: 'prepaid',
        tier: $this->tier1,
        cashier: $this->cashier,
        allocatedMinutes: 60,
        customerName: 'Ahmed'
    );

    expect($session->id)->not->toBeNull()
        ->and($session->status)->toBe('active')
        ->and($session->session_type)->toBe('prepaid')
        ->and($session->allocated_minutes)->toBe(60)
        ->and($this->station1->fresh()->current_state)->toBe('active_prepaid')
        ->and($this->station1->fresh()->tv_physical_state)->toBe('screen_on');
});

test('it switches player tiers and closes previous interval with subtotal', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station1,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier
    );

    // Fast-forward initial interval
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(30)]);

    $newInterval = $this->sessionManager->switchTier($session, $this->tier2);

    expect($session->intervals()->count())->toBe(2)
        ->and($session->intervals()->first()->ended_at)->not->toBeNull()
        ->and($session->intervals()->first()->billable_minutes)->toBe(30)
        ->and($session->intervals()->first()->subtotal_millimes)->toBe(3000)
        ->and($newInterval->pricing_tier_id)->toBe($this->tier2->id)
        ->and($newInterval->ended_at)->toBeNull();
});

test('it pauses and resumes session and accumulates paused seconds', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station1,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier
    );

    $this->sessionManager->pauseSession($session, 'Customer Break');

    expect($session->fresh()->status)->toBe('paused')
        ->and($this->station1->fresh()->current_state)->toBe('paused')
        ->and($this->station1->fresh()->tv_physical_state)->toBe('standby');

    // Simulate 10 minutes paused
    $session->update(['paused_at' => now()->subMinutes(10)]);

    $this->sessionManager->resumeSession($session);

    expect($session->fresh()->status)->toBe('active')
        ->and($session->fresh()->total_paused_seconds)->toBeGreaterThanOrEqual(590)
        ->and($this->station1->fresh()->current_state)->toBe('active_postpaid')
        ->and($this->station1->fresh()->tv_physical_state)->toBe('screen_on');
});

test('it ends session, puts TV to standby, and settles payment with cash change', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station1,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier
    );

    // Set 60 minutes played
    $session->update(['started_at' => now()->subMinutes(60)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $this->sessionManager->endSession($session);

    expect($session->fresh()->status)->toBe('payment_pending')
        ->and($this->station1->fresh()->current_state)->toBe('payment_pending')
        ->and($this->station1->fresh()->tv_physical_state)->toBe('standby');

    // Settle 6.000 LYD bill with 10.000 LYD cash -> 4.000 LYD change
    $this->sessionManager->settlePayment($session, 'cash', 10000);

    expect($session->fresh()->status)->toBe('completed')
        ->and($session->fresh()->payment_status)->toBe('paid')
        ->and($session->fresh()->final_total_millimes)->toBe(6000)
        ->and($session->fresh()->cash_received_millimes)->toBe(10000)
        ->and($session->fresh()->cash_change_millimes)->toBe(4000)
        ->and($this->station1->fresh()->current_state)->toBe('available');
});

test('it transfers session to available station and updates states', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station1,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier
    );

    $this->sessionManager->transferStation($session, $this->station2);

    expect($session->fresh()->station_id)->toBe($this->station2->id)
        ->and($this->station1->fresh()->current_state)->toBe('available')
        ->and($this->station1->fresh()->tv_physical_state)->toBe('standby')
        ->and($this->station2->fresh()->current_state)->toBe('active_postpaid')
        ->and($this->station2->fresh()->tv_physical_state)->toBe('screen_on');
});
