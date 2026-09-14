<?php

use App\Console\Commands\ExpirePrepaidSessionsCommand;
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
        'tv_control_enabled' => true,
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

test('tvs:reconcile command runs successfully and flags rogue stations', function () {
    $this->station->update(['tv_physical_state' => 'screen_on']);

    $this->artisan('tvs:reconcile')
        ->expectsOutputToContain('Reconciled 1 stations')
        ->assertExitCode(0);
});

test('tvs:reconcile fails for unknown station id', function () {
    $this->artisan('tvs:reconcile', ['--station' => 9999])
        ->expectsOutputToContain('not found')
        ->assertExitCode(1);
});

test('sessions:expire-prepaid command auto-ends expired sessions', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'prepaid',
        tier: $this->tier,
        cashier: $this->cashier,
        allocatedMinutes: 30,
    );

    $session->update(['started_at' => now()->subMinutes(45)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(45)]);

    $this->artisan(ExpirePrepaidSessionsCommand::class)
        ->assertExitCode(0);

    expect($session->fresh()->status)->toBe('payment_pending')
        ->and($this->station->fresh()->current_state)->toBe('payment_pending');
});

test('sessions:expire-prepaid does not end sessions still within allocated time', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'prepaid',
        tier: $this->tier,
        cashier: $this->cashier,
        allocatedMinutes: 60,
    );

    $this->artisan(ExpirePrepaidSessionsCommand::class)->assertExitCode(0);

    expect($session->fresh()->status)->toBe('active');
});

test('sessions:expire-prepaid respects --dry-run flag', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'prepaid',
        tier: $this->tier,
        cashier: $this->cashier,
        allocatedMinutes: 10,
    );

    $session->update(['started_at' => now()->subMinutes(20)]);
    $session->intervals()->first()->update(['started_at' => now()->subMinutes(20)]);

    $this->artisan(ExpirePrepaidSessionsCommand::class, ['--dry-run' => true])
        ->expectsOutputToContain('[dry-run]')
        ->assertExitCode(0);

    expect($session->fresh()->status)->toBe('active');
});
