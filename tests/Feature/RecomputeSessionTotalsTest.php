<?php

use App\Models\GameSession;
use App\Models\GameSessionInterval;
use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->rule = PricingRule::create([
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
});

test('it records theoretical total without overwriting the billed total', function () {
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'final_total_millimes' => 6000, // original (over-billed) figure
        'time_amount_millimes' => 6000,
        'retail_amount_millimes' => 0,
        'discount_amount_millimes' => 0,
    ]);

    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier->id,
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'duration_seconds' => 3600,
        'billable_minutes' => 60,
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
        'subtotal_millimes' => 6000,
    ]);

    $this->artisan('sessions:recompute-totals')->assertExitCode(0);

    $session->refresh();

    expect($session->final_total_millimes)->toBe(6000) // untouched
        ->and($session->previous_final_total_millimes)->toBe(6000)
        ->and($session->recomputed_at)->not->toBeNull();
});

test('it backfills the pause-aware theoretical total for a paused session', function () {
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'total_paused_seconds' => 600,
        'final_total_millimes' => 6000, // over-billed: should have been 5000
        'time_amount_millimes' => 6000,
        'retail_amount_millimes' => 0,
        'discount_amount_millimes' => 0,
    ]);

    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier->id,
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'duration_seconds' => 3600,
        'billable_minutes' => 60,
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
        'subtotal_millimes' => 6000,
    ]);

    $this->artisan('sessions:recompute-totals')->assertExitCode(0);

    $session->refresh();

    expect($session->final_total_millimes)->toBe(6000)
        ->and($session->previous_final_total_millimes)->toBe(5000)
        ->and($session->recomputedDeltaMillimes())->toBe(-1000);
});

test('dry-run does not persist changes', function () {
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'total_paused_seconds' => 600,
        'final_total_millimes' => 6000,
        'time_amount_millimes' => 6000,
        'retail_amount_millimes' => 0,
        'discount_amount_millimes' => 0,
    ]);

    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier->id,
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'duration_seconds' => 3600,
        'billable_minutes' => 60,
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
        'subtotal_millimes' => 6000,
    ]);

    $this->artisan('sessions:recompute-totals', ['--dry-run' => true])
        ->expectsOutputToContain('[dry-run]')
        ->assertExitCode(0);

    $session->refresh();

    expect($session->previous_final_total_millimes)->toBeNull()
        ->and($session->recomputed_at)->toBeNull();
});

test('recompute is idempotent', function () {
    $session = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'total_paused_seconds' => 600,
        'final_total_millimes' => 6000,
        'time_amount_millimes' => 6000,
        'retail_amount_millimes' => 0,
        'discount_amount_millimes' => 0,
    ]);

    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier->id,
        'started_at' => now()->subMinutes(60),
        'ended_at' => now(),
        'duration_seconds' => 3600,
        'billable_minutes' => 60,
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
        'subtotal_millimes' => 6000,
    ]);

    $this->artisan('sessions:recompute-totals')->assertExitCode(0);
    $firstPrevious = $session->fresh()->previous_final_total_millimes;
    $firstTime = $session->fresh()->recomputed_at;

    $this->artisan('sessions:recompute-totals')->assertExitCode(0);
    $session->refresh();

    expect($session->previous_final_total_millimes)->toBe($firstPrevious)
        ->and($session->recomputed_at->toIso8601String())->toBe($firstTime->toIso8601String());
});

test('only completed sessions are recomputed', function () {
    $active = GameSession::create([
        'station_id' => $this->station->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'active',
        'started_at' => now()->subMinutes(60),
        'final_total_millimes' => 0,
        'time_amount_millimes' => 0,
    ]);

    GameSessionInterval::create([
        'game_session_id' => $active->id,
        'pricing_tier_id' => $this->tier->id,
        'started_at' => now()->subMinutes(60),
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
    ]);

    $this->artisan('sessions:recompute-totals')->assertExitCode(0);

    expect($active->fresh()->previous_final_total_millimes)->toBeNull();
});
