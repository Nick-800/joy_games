<?php

use App\Models\GameSession;
use App\Models\GameSessionInterval;
use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        'prepaid_overtime_grace_minutes' => 5,
        'allow_overtime_default' => true,
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
        'tv_os_type' => 'simulated',
    ]);

    $this->actingAs($this->cashier);
});

function makeCompleted(int $stationId, int $cashierId, int $tierId, int $finalLyd, string $endedAt, string $paymentMethod = 'cash', int $discountLyd = 0): GameSession
{
    $session = GameSession::create([
        'station_id' => $stationId,
        'cashier_id' => $cashierId,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => CarbonImmutable::parse($endedAt)->subMinutes(60),
        'ended_at' => CarbonImmutable::parse($endedAt),
        'final_total_millimes' => $finalLyd,
        'time_amount_millimes' => $finalLyd + $discountLyd,
        'retail_amount_millimes' => 0,
        'discount_amount_millimes' => $discountLyd,
        'payment_status' => 'paid',
        'payment_method' => $paymentMethod,
    ]);

    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $tierId,
        'started_at' => $session->started_at,
        'ended_at' => $session->ended_at,
        'duration_seconds' => 3600,
        'billable_minutes' => 60,
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
        'subtotal_millimes' => 6000,
    ]);

    return $session;
}

test('GET /reports renders the Reports page', function () {
    $this->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reports'));
});

test('GET /reports/data returns aggregated JSON', function () {
    makeCompleted($this->station->id, $this->cashier->id, $this->tier->id, 6000, '2026-09-10 12:00:00');

    $this->get('/reports/data?mode=daily&from=2026-09-10&to=2026-09-10')
        ->assertOk()
        ->assertJsonPath('mode', 'daily')
        ->assertJsonPath('timezone', 'Africa/Tripoli')
        ->assertJsonPath('totals.final_lyd', 6000)
        ->assertJsonPath('totals.sessions', 1);
});

test('GET /reports/sessions returns the bucket session list', function () {
    $session = makeCompleted($this->station->id, $this->cashier->id, $this->tier->id, 6000, '2026-09-10 12:00:00');

    $this->get('/reports/sessions?mode=daily&key=2026-09-10')
        ->assertOk()
        ->assertJsonPath('sessions.0.id', $session->id)
        ->assertJsonPath('sessions.0.station_name', 'PS5 Station 01')
        ->assertJsonPath('sessions.0.cashier_name', $this->cashier->name);
});

test('GET /reports/export streams CSV with proper headers', function () {
    makeCompleted($this->station->id, $this->cashier->id, $this->tier->id, 6000, '2026-09-10 12:00:00');

    $response = $this->get('/reports/export?mode=daily&from=2026-09-10&to=2026-09-10');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Bucket,Sessions')
        ->and($csv)->toContain('2026-09-10,1');
});

test('filters narrow the result set', function () {
    $station2 = Station::create(['name' => 'PS5 Station 02', 'station_number' => 2, 'type' => 'standard', 'tv_os_type' => 'simulated']);

    makeCompleted($this->station->id, $this->cashier->id, $this->tier->id, 6000, '2026-09-10 12:00:00');
    makeCompleted($station2->id, $this->cashier->id, $this->tier->id, 3000, '2026-09-10 13:00:00');

    $this->get('/reports/data?mode=daily&from=2026-09-10&to=2026-09-10&station_ids[]='.$this->station->id)
        ->assertOk()
        ->assertJsonPath('totals.final_lyd', 6000)
        ->assertJsonPath('totals.sessions', 1);
});

test('reports route requires authentication', function () {
    auth()->logout();
    $this->get('/reports')->assertRedirect('/login');
});
