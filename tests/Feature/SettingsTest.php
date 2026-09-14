<?php

use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use App\Models\User;
use App\Services\Sessions\SessionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    PricingRule::create([
        'grace_period_minutes' => 3,
        'minimum_charge_minutes' => 15,
        'rounding_step_minutes' => 5,
        'vip_multiplier' => 1.50,
        'rogue_auto_sleep_seconds' => 120,
        'currency_code' => 'LYD',
        'currency_symbol' => 'د.ل',
        'timezone' => 'Africa/Tripoli',
        'tv_control_enabled' => false,
    ]);

    $this->tier = PricingTier::create([
        'name' => '1–2 Players',
        'controller_count_min' => 1,
        'controller_count_max' => 2,
        'hourly_rate_millimes' => 6000,
        'is_active' => true,
        'display_order' => 1,
    ]);

    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->actingAs($this->cashier);
});

test('GET /settings renders the Settings page with stations and rules', function () {
    Station::create([
        'name' => 'PS5 Station 01',
        'station_number' => 1,
        'type' => 'vip',
        'default_hourly_rate_millimes' => 15000,
        'available_games' => ['EA Sports FC 25', 'Tekken 8'],
        'tv_physical_state' => 'standby',
        'current_state' => 'available',
        'tv_os_type' => 'simulated',
    ]);

    $response = $this->get('/settings');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings')
            ->has('stations', 1)
            ->where('stations.0.is_vip', true)
            ->where('stations.0.default_hourly_rate_lyd', 15)
            ->where('stations.0.available_games', ['EA Sports FC 25', 'Tekken 8'])
            ->where('pricingRule.tv_control_enabled', false)
        );
});

test('POST /settings/stations creates a new station with default rate and games', function () {
    $response = $this->post('/settings/stations', [
        'name' => 'PS5 Station VIP 01',
        'station_number' => 9,
        'type' => 'vip',
        'default_hourly_rate_lyd' => 20,
        'available_games' => ['EA Sports FC 25', 'Mortal Kombat 1'],
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $station = Station::where('station_number', 9)->first();
    expect($station)->not->toBeNull()
        ->and($station->name)->toBe('PS5 Station VIP 01')
        ->and($station->type)->toBe('vip')
        ->and($station->default_hourly_rate_millimes)->toBe(20000)
        ->and($station->default_hourly_rate_lyd)->toBe(20)
        ->and($station->available_games)->toBe(['EA Sports FC 25', 'Mortal Kombat 1']);
});

test('POST /settings/stations rejects prices that are not multiples of 5 LYD', function () {
    $response = $this->post('/settings/stations', [
        'name' => 'PS5 Station 02',
        'station_number' => 2,
        'type' => 'standard',
        'default_hourly_rate_lyd' => 12, // Not a multiple of 5
        'available_games' => ['EA Sports FC 25'],
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors(['default_hourly_rate_lyd']);
    expect(Station::where('station_number', 2)->exists())->toBeFalse();
});

test('PUT /settings/stations/{station} updates station details', function () {
    $station = Station::create([
        'name' => 'Old Name',
        'station_number' => 3,
        'type' => 'standard',
        'default_hourly_rate_millimes' => 10000,
        'available_games' => ['EA Sports FC 25'],
        'tv_physical_state' => 'standby',
        'current_state' => 'available',
        'tv_os_type' => 'simulated',
    ]);

    $response = $this->put("/settings/stations/{$station->id}", [
        'name' => 'Station 03 (VIP Upgrade)',
        'station_number' => 3,
        'type' => 'vip',
        'default_hourly_rate_lyd' => 15,
        'available_games' => ['EA Sports FC 25', 'Tekken 8', 'Grand Theft Auto V'],
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $fresh = $station->fresh();
    expect($fresh->name)->toBe('Station 03 (VIP Upgrade)')
        ->and($fresh->type)->toBe('vip')
        ->and($fresh->default_hourly_rate_millimes)->toBe(15000)
        ->and($fresh->default_hourly_rate_lyd)->toBe(15)
        ->and($fresh->available_games)->toHaveCount(3);
});

test('DELETE /settings/stations/{station} deletes an idle station', function () {
    $station = Station::create([
        'name' => 'To Delete',
        'station_number' => 88,
        'type' => 'standard',
        'tv_physical_state' => 'standby',
        'current_state' => 'available',
        'tv_os_type' => 'simulated',
    ]);

    $response = $this->delete("/settings/stations/{$station->id}");

    $response->assertRedirect();
    expect(Station::where('id', $station->id)->exists())->toBeFalse();
});

test('DELETE /settings/stations/{station} blocks deletion when station has active in-play session', function () {
    $station = Station::create([
        'name' => 'Active Station',
        'station_number' => 4,
        'type' => 'standard',
        'tv_physical_state' => 'standby',
        'current_state' => 'available',
        'tv_os_type' => 'simulated',
    ]);

    $sessionManager = app(SessionManager::class);
    $sessionManager->startSession(
        station: $station,
        sessionType: 'postpaid',
        tier: $this->tier,
        cashier: $this->cashier,
    );

    $response = $this->delete("/settings/stations/{$station->id}");

    $response->assertSessionHas('error');
    expect(Station::where('id', $station->id)->exists())->toBeTrue();
});

test('POST /settings/feature-flags toggles tv_control_enabled on and off', function () {
    expect(PricingRule::current()->tv_control_enabled)->toBeFalse();

    $this->post('/settings/feature-flags', ['tv_control_enabled' => true])
        ->assertRedirect();

    expect(PricingRule::current()->fresh()->tv_control_enabled)->toBeTrue();

    $this->post('/settings/feature-flags', ['tv_control_enabled' => false])
        ->assertRedirect();

    expect(PricingRule::current()->fresh()->tv_control_enabled)->toBeFalse();
});

test('POST /settings/stations creates a station with custom 1-2 and 3-4 player rates', function () {
    $response = $this->post('/settings/stations', [
        'name' => 'PS5 Lounge Duo',
        'station_number' => 12,
        'type' => 'vip',
        'hourly_rate_1_2_lyd' => 15,
        'hourly_rate_3_4_lyd' => 25,
        'available_games' => ['EA Sports FC 25'],
        'is_active' => true,
    ]);

    $response->assertRedirect();

    $station = Station::where('station_number', 12)->first();
    expect($station)->not->toBeNull()
        ->and($station->hourly_rate_1_2_millimes)->toBe(15000)
        ->and($station->hourly_rate_1_2_lyd)->toBe(15)
        ->and($station->hourly_rate_3_4_millimes)->toBe(25000)
        ->and($station->hourly_rate_3_4_lyd)->toBe(25);
});

test('PUT /settings/pricing-tiers/{tier} updates global fallback rate in multiples of 5', function () {
    $response = $this->put("/settings/pricing-tiers/{$this->tier->id}", [
        'hourly_rate_lyd' => 15,
    ]);

    $response->assertRedirect();
    expect($this->tier->fresh()->hourly_rate_millimes)->toBe(15000)
        ->and($this->tier->fresh()->hourly_rate_lyd)->toBe(15.0);

    // Rejects non-multiples of 5
    $this->put("/settings/pricing-tiers/{$this->tier->id}", [
        'hourly_rate_lyd' => 12,
    ])->assertSessionHasErrors(['hourly_rate_lyd']);
});

test('startSession uses station-defined 1-2 and 3-4 player rates when starting session', function () {
    $station = Station::create([
        'name' => 'Custom Rate Station',
        'station_number' => 15,
        'type' => 'standard',
        'hourly_rate_1_2_millimes' => 10000, // 10 LYD for 1-2 players
        'hourly_rate_3_4_millimes' => 20000, // 20 LYD for 3-4 players
        'tv_physical_state' => 'standby',
        'current_state' => 'available',
        'tv_os_type' => 'simulated',
    ]);

    $tier34 = PricingTier::create([
        'name' => '3–4 Players',
        'controller_count_min' => 3,
        'controller_count_max' => 4,
        'hourly_rate_millimes' => 10000,
        'is_active' => true,
        'display_order' => 2,
    ]);

    $sessionManager = app(SessionManager::class);

    // 1-2 players session
    $session12 = $sessionManager->startSession(
        station: $station,
        sessionType: 'postpaid',
        tier: $this->tier, // 1-2 players
        cashier: $this->cashier,
    );

    expect($session12->intervals->first()->rate_per_hour_millimes)->toBe(10000);

    // Settle to free the station
    $sessionManager->endSession($session12);
    $sessionManager->settlePayment($session12, 'cash', 10000);

    // 3-4 players session
    $session34 = $sessionManager->startSession(
        station: $station,
        sessionType: 'postpaid',
        tier: $tier34, // 3-4 players
        cashier: $this->cashier,
    );

    expect($session34->intervals->first()->rate_per_hour_millimes)->toBe(20000);
});

test('switch-tier route applies station-defined player tier rates dynamically', function () {
    $station = Station::create([
        'name' => 'Station Switch Test',
        'station_number' => 16,
        'type' => 'standard',
        'hourly_rate_1_2_millimes' => 10000,
        'hourly_rate_3_4_millimes' => 25000,
        'tv_physical_state' => 'standby',
        'current_state' => 'available',
        'tv_os_type' => 'simulated',
    ]);

    $tier34 = PricingTier::create([
        'name' => '3–4 Players',
        'controller_count_min' => 3,
        'controller_count_max' => 4,
        'hourly_rate_millimes' => 10000,
        'is_active' => true,
        'display_order' => 2,
    ]);

    $sessionManager = app(SessionManager::class);
    $session = $sessionManager->startSession(
        station: $station,
        sessionType: 'postpaid',
        tier: $this->tier,
        cashier: $this->cashier,
    );

    $this->post("/sessions/{$session->id}/switch-tier", [
        'pricing_tier_id' => $tier34->id,
    ])->assertRedirect();

    $newInterval = $session->fresh()->intervals()->latest('id')->first();
    expect($newInterval->rate_per_hour_millimes)->toBe(25000)
        ->and((float) $newInterval->station_multiplier)->toBe(1.0);
});
