<?php

use App\Models\GameSession;
use App\Models\OrderItem;
use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Product;
use App\Models\Shift;
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
        'currency_code' => 'LYD',
        'currency_symbol' => 'د.ل',
        'allow_overtime_default' => true,
        'tv_control_enabled' => false,
    ]);

    $this->tier1 = PricingTier::create([
        'name' => '1–2 Players',
        'controller_count_min' => 1,
        'controller_count_max' => 2,
        'hourly_rate_millimes' => 10000, // 10 LYD/hr
    ]);

    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->shift = Shift::create([
        'user_id' => $this->cashier->id,
        'started_at' => now(),
        'opening_float_millimes' => 50000,
        'status' => 'open',
    ]);

    $this->station = Station::create([
        'name' => 'Station Alpha',
        'station_number' => 1,
        'type' => 'standard',
        'current_state' => 'available',
        'hourly_rate_1_2_millimes' => 10000,
        'hourly_rate_3_4_millimes' => 15000,
    ]);
});

test('it starts a prepaid session with pay before starts (upfront) and sets payment_status to paid', function () {
    $this->actingAs($this->cashier);

    // 1 hour at 10 LYD/hr = 10 LYD. Cashier enters 20 LYD cash.
    $response = $this->post('/sessions/start', [
        'station_id' => $this->station->id,
        'session_type' => 'prepaid',
        'pricing_tier_id' => $this->tier1->id,
        'allocated_minutes' => 60,
        'prepaid_payment_timing' => 'before',
        'cash_received_millimes' => 20000,
    ]);

    $response->assertRedirect();

    $session = GameSession::first();
    expect($session)->not->toBeNull()
        ->and($session->session_type)->toBe('prepaid')
        ->and($session->prepaid_payment_timing)->toBe('before')
        ->and($session->payment_status)->toBe('paid')
        ->and($session->payment_method)->toBe('cash')
        ->and($session->upfront_paid_millimes)->toBe(10000)
        ->and($session->upfront_paid_lyd)->toBe(10)
        ->and($session->final_total_millimes)->toBe(10000)
        ->and($session->cash_received_millimes)->toBe(20000)
        ->and($session->cash_change_millimes)->toBe(10000)
        ->and($this->station->fresh()->current_state)->toBe('active_prepaid');
});

test('it starts a prepaid session with pay after ends and leaves payment_status as unpaid', function () {
    $this->actingAs($this->cashier);

    $response = $this->post('/sessions/start', [
        'station_id' => $this->station->id,
        'session_type' => 'prepaid',
        'pricing_tier_id' => $this->tier1->id,
        'allocated_minutes' => 60,
        'prepaid_payment_timing' => 'after',
    ]);

    $response->assertRedirect();

    $session = GameSession::first();
    expect($session)->not->toBeNull()
        ->and($session->session_type)->toBe('prepaid')
        ->and($session->prepaid_payment_timing)->toBe('after')
        ->and($session->payment_status)->toBe('unpaid')
        ->and($session->payment_method)->toBeNull()
        ->and($session->upfront_paid_millimes)->toBe(0)
        ->and($session->upfront_paid_lyd)->toBe(0);
});

test('it starts a postpaid session with no prepaid timing and unpaid status', function () {
    $this->actingAs($this->cashier);

    $response = $this->post('/sessions/start', [
        'station_id' => $this->station->id,
        'session_type' => 'postpaid',
        'pricing_tier_id' => $this->tier1->id,
    ]);

    $response->assertRedirect();

    $session = GameSession::first();
    expect($session)->not->toBeNull()
        ->and($session->session_type)->toBe('postpaid')
        ->and($session->prepaid_payment_timing)->toBeNull()
        ->and($session->payment_status)->toBe('unpaid');
});

test('it checks out an upfront-paid prepaid session with no extra items requiring 0 additional cash', function () {
    $this->actingAs($this->cashier);

    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'prepaid',
        tier: $this->tier1,
        cashier: $this->cashier,
        allocatedMinutes: 60,
        prepaidPaymentTiming: 'before',
        cashReceivedMillimes: 10000
    );

    // Fast-forward 60 minutes
    $session->update(['started_at' => now()->subMinutes(60)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $this->sessionManager->endSession($session);

    // Settle with 0 additional cash because it was already paid upfront
    $response = $this->post("/sessions/{$session->id}/settle", [
        'payment_method' => 'cash',
        'cash_received_millimes' => 0,
    ]);

    $response->assertRedirect();

    $freshSession = $session->fresh();
    expect($freshSession->status)->toBe('completed')
        ->and($freshSession->payment_status)->toBe('paid')
        ->and($freshSession->final_total_millimes)->toBe(10000)
        ->and($freshSession->cash_received_millimes)->toBe(10000)
        ->and($this->station->fresh()->current_state)->toBe('available');
});

test('it checks out an upfront-paid prepaid session with retail items collecting only remaining retail balance', function () {
    $this->actingAs($this->cashier);

    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'prepaid',
        tier: $this->tier1,
        cashier: $this->cashier,
        allocatedMinutes: 60,
        prepaidPaymentTiming: 'before',
        cashReceivedMillimes: 10000
    );

    // Add 5 LYD retail snack
    $product = Product::create([
        'name' => 'Energy Drink',
        'sku' => 'NRG-01',
        'category' => 'Drink',
        'retail_price_millimes' => 5000,
        'cost_price_millimes' => 3000,
        'stock_quantity' => 20,
        'is_active' => true,
    ]);

    OrderItem::create([
        'game_session_id' => $session->id,
        'product_id' => $product->id,
        'shift_id' => $this->shift->id,
        'item_name' => $product->name,
        'quantity' => 1,
        'unit_price_millimes' => 5000,
        'subtotal_millimes' => 5000,
    ]);

    $session->update(['started_at' => now()->subMinutes(60)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(60)]);

    $this->sessionManager->endSession($session);

    // Settle with 10 LYD cash for the 5 LYD remaining retail balance -> 5 LYD change
    $response = $this->post("/sessions/{$session->id}/settle", [
        'payment_method' => 'cash',
        'cash_received_millimes' => 10000,
    ]);

    $response->assertRedirect();

    $freshSession = $session->fresh();
    expect($freshSession->status)->toBe('completed')
        ->and($freshSession->payment_status)->toBe('paid')
        ->and($freshSession->final_total_millimes)->toBe(15000)
        ->and($freshSession->cash_received_millimes)->toBe(20000) // 10k upfront + 10k at checkout
        ->and($freshSession->cash_change_millimes)->toBe(5000)
        ->and($this->station->fresh()->current_state)->toBe('available');
});

test('it passes payment_status and upfront_paid_lyd to dashboard props', function () {
    $this->actingAs($this->cashier);

    $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'prepaid',
        tier: $this->tier1,
        cashier: $this->cashier,
        allocatedMinutes: 60,
        prepaidPaymentTiming: 'before',
        cashReceivedMillimes: 10000
    );

    $response = $this->get('/');
    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->has('stations', 1)
        ->where('stations.0.active_session.payment_status', 'paid')
        ->where('stations.0.active_session.prepaid_payment_timing', 'before')
        ->where('stations.0.active_session.upfront_paid_lyd', 10)
    );
});
