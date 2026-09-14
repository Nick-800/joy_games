<?php

use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use App\Models\StationAudit;
use App\Models\User;
use App\Services\Sessions\SessionManager;
use App\Services\Tv\TvReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reconciliation = app(TvReconciliationService::class);
    $this->sessionManager = app(SessionManager::class);

    PricingRule::create([
        'grace_period_minutes' => 3,
        'minimum_charge_minutes' => 15,
        'rounding_step_minutes' => 5,
        'vip_multiplier' => 1.50,
        'rogue_auto_sleep_seconds' => 120, // 2 minutes
        'currency_code' => 'LYD',
        'currency_symbol' => 'د.ل',
        'allow_overtime_default' => true,
        'tv_control_enabled' => true,
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
        'tv_ip_address' => '192.168.1.101',
        'tv_mac_address' => '00:11:22:33:44:01',
        'tv_os_type' => 'simulated',
        'tv_physical_state' => 'standby',
        'current_state' => 'available',
    ]);
});

test('it debounces rogue TV power-on detection requiring 2 consecutive on pings', function () {
    // Ping 1: TV Screen becomes ON on available station
    $this->station->update(['tv_physical_state' => 'screen_on']);
    $res1 = $this->reconciliation->reconcileStation($this->station);

    expect($this->station->fresh()->consecutive_on_pings)->toBe(1)
        ->and($this->station->fresh()->isRogue())->toBeFalse()
        ->and($res1['action_taken'])->toBe('none');

    // Ping 2: Still screen_on -> Rogue confirmed!
    $res2 = $this->reconciliation->reconcileStation($this->station);

    expect($this->station->fresh()->consecutive_on_pings)->toBe(2)
        ->and($this->station->fresh()->isRogue())->toBeTrue()
        ->and($res2['action_taken'])->toBe('flagged_rogue');
});

test('it auto-pauses active session if TV display unexpectedly enters standby', function () {
    $session = $this->sessionManager->startSession(
        station: $this->station,
        sessionType: 'postpaid',
        tier: $this->tier1,
        cashier: $this->cashier
    );

    // Simulate TV screen unexpectedly turned off (e.g. remote power button)
    $this->station->update(['tv_physical_state' => 'standby']);

    $res = $this->reconciliation->reconcileStation($this->station);

    expect($res['action_taken'])->toBe('auto_paused_session')
        ->and($session->fresh()->isPaused())->toBeTrue()
        ->and($this->station->fresh()->current_state)->toBe('paused')
        ->and(StationAudit::where('event_type', 'unexpected_tv_off')->exists())->toBeTrue();
});

test('it enforces auto-blackout guardrail when rogue TV power-on exceeds 2-minute timeout', function () {
    $this->station->update([
        'tv_physical_state' => 'screen_on',
        'consecutive_on_pings' => 2,
        'first_detected_on_at' => now()->subSeconds(150), // > 120s timeout
    ]);

    $res = $this->reconciliation->reconcileStation($this->station->fresh());

    expect($res['action_taken'])->toBe('auto_blackout_enforced')
        ->and($this->station->fresh()->tv_physical_state)->toBe('standby')
        ->and($this->station->fresh()->consecutive_on_pings)->toBe(0)
        ->and(StationAudit::where('event_type', 'auto_blackout_triggered')->exists())->toBeTrue();
});
