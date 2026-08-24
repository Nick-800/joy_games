<?php

use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Product;
use App\Models\Station;
use App\Models\User;
use App\Services\Sessions\SessionManager;
use App\Services\Shifts\ShiftLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->shiftLedger = app(ShiftLedger::class);
    $this->sessionManager = app(SessionManager::class);

    PricingRule::create([
        'currency_code' => 'LYD',
        'currency_symbol' => 'د.ل',
    ]);

    $this->tier = PricingTier::create([
        'name' => '1–2 Players',
        'controller_count_min' => 1,
        'controller_count_max' => 2,
        'hourly_rate_millimes' => 6000,
    ]);

    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->station = Station::create(['name' => 'PS5 Station 01', 'station_number' => 1, 'type' => 'standard']);

    $this->product1 = Product::create([
        'name' => 'Red Bull 250ml',
        'category' => 'beverage',
        'price_millimes' => 4500,
        'stock_quantity' => 10,
    ]);
});

test('it opens a shift with cash float in LYD', function () {
    $shift = $this->shiftLedger->openShift($this->cashier, 150000); // 150.000 LYD

    expect($shift->isOpen())->toBeTrue()
        ->and($shift->opening_float_millimes)->toBe(150000)
        ->and($shift->user_id)->toBe($this->cashier->id);
});

test('it attaches retail order items to a session and decrements stock', function () {
    $shift = $this->shiftLedger->openShift($this->cashier, 100000);

    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier,
        cashier: $this->cashier
    );

    $this->actingAs($this->cashier)->post(route('pos.items.add'), [
        'product_id' => $this->product1->id,
        'game_session_id' => $session->id,
        'quantity' => 2,
    ]);

    expect($session->orderItems()->count())->toBe(1)
        ->and($session->orderItems()->first()->quantity)->toBe(2)
        ->and($session->orderItems()->first()->subtotal_millimes)->toBe(9000) // 9.000 LYD
        ->and($this->product1->fresh()->stock_quantity)->toBe(8);
});

test('it closes shift with blind drop and records cash difference', function () {
    $shift = $this->shiftLedger->openShift($this->cashier, 100000); // 100.000 LYD float

    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier,
        cashier: $this->cashier
    );

    // End and settle 20.000 LYD cash payment
    $session->update(['started_at' => now()->subMinutes(60)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);
    $this->sessionManager->endSession($session);
    $this->sessionManager->settlePayment($session, 'cash', 6000); // 6.000 LYD session cash collected

    // Total expected in drawer: 100.000 float + 6.000 cash = 106.000 LYD
    // Cashier counts 105.000 LYD (1.000 LYD short)
    $closedShift = $this->shiftLedger->closeShift($shift, 105000);

    expect($closedShift->status)->toBe('closed')
        ->and($closedShift->expected_cash_millimes)->toBe(106000)
        ->and($closedShift->closing_cash_counted_millimes)->toBe(105000)
        ->and($closedShift->cash_difference_millimes)->toBe(-1000); // -1.000 LYD deficit
});
