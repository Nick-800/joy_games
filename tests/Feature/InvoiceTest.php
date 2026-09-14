<?php

use App\Models\GameSession;
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
    $this->station = Station::create([
        'name' => 'PS5 Station 01',
        'station_number' => 1,
        'type' => 'standard',
        'current_state' => 'available',
        'tv_physical_state' => 'standby',
        'tv_os_type' => 'simulated',
    ]);
});

test('authenticated user can view invoice for a completed session', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier,
        cashier: $this->cashier,
    );

    $session->update(['started_at' => now()->subMinutes(30)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(30)]);

    $this->sessionManager->endSession($session);
    $this->sessionManager->settlePayment($session, 'cash', 10000);

    $this->actingAs($this->cashier)
        ->get(route('sessions.invoice', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Invoice')
            ->where('session.id', $session->id)
            ->where('totals.final_total_lyd', 3)
        );
});

test('unauthenticated user is redirected when trying to view an invoice', function () {
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => now()->subMinutes(10),
    ]);

    $this->get(route('sessions.invoice', $session))->assertRedirect('/login');
});
