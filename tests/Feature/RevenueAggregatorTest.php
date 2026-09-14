<?php

use App\Models\GameSession;
use App\Models\GameSessionInterval;
use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Station;
use App\Models\User;
use App\Services\Reports\Bucket;
use App\Services\Reports\Filters;
use App\Services\Reports\RevenueAggregator;
use Carbon\CarbonImmutable;
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
    $this->station1 = Station::create(['name' => 'PS5 Station 01', 'station_number' => 1, 'type' => 'standard', 'tv_os_type' => 'simulated']);
    $this->station2 = Station::create(['name' => 'PS5 Station 02', 'station_number' => 2, 'type' => 'standard', 'tv_os_type' => 'simulated']);

    $this->aggregator = app(RevenueAggregator::class);
});

function makeCompletedSession(int $stationId, int $cashierId, int $tierId, int $finalLyd, string $endedAt, string $paymentMethod = 'cash', int $retailLyd = 0, int $discountLyd = 0): GameSession
{
    $session = GameSession::create([
        'station_id' => $stationId,
        'cashier_id' => $cashierId,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => CarbonImmutable::parse($endedAt)->subMinutes(60),
        'ended_at' => CarbonImmutable::parse($endedAt),
        'final_total_millimes' => $finalLyd,
        'time_amount_millimes' => $finalLyd - $retailLyd + $discountLyd,
        'retail_amount_millimes' => $retailLyd,
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

test('daily mode splits revenue across the correct local day buckets', function () {
    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 6000,
        endedAt: '2026-09-10 14:30:00',
    );
    makeCompletedSession(
        stationId: $this->station2->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 9000,
        endedAt: '2026-09-11 10:00:00',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-12')->endOfDay(),
        Filters::fromArray([]),
    );

    expect($report['mode'])->toBe('daily')
        ->and($report['timezone'])->toBe('Africa/Tripoli')
        ->and($report['totals']['final_lyd'])->toBe(15000)
        ->and($report['totals']['sessions'])->toBe(2);

    $keys = collect($report['buckets'])->pluck('key')->all();
    expect($keys)->toContain('2026-09-10')
        ->and($keys)->toContain('2026-09-11');

    $day1 = collect($report['buckets'])->firstWhere('key', '2026-09-10');
    $day2 = collect($report['buckets'])->firstWhere('key', '2026-09-11');

    expect($day1['final_lyd'])->toBe(6000)
        ->and($day1['sessions'])->toBe(1)
        ->and($day2['final_lyd'])->toBe(9000)
        ->and($day2['sessions'])->toBe(1);
});

test('weekly mode aggregates into ISO Monday-start buckets', function () {
    // Monday Sep 7 and Sunday Sep 13 (20:00 UTC = 22:00 local) are in the same week.
    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 3000,
        endedAt: '2026-09-07 09:00:00',
    );
    makeCompletedSession(
        stationId: $this->station2->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 5000,
        endedAt: '2026-09-13 20:00:00',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_WEEKLY,
        CarbonImmutable::parse('2026-09-07')->startOfDay(),
        CarbonImmutable::parse('2026-09-13')->endOfDay(),
        Filters::fromArray([]),
    );

    $week = collect($report['buckets'])->firstWhere(fn ($b) => $b['final_lyd'] > 0);
    expect($week['final_lyd'])->toBe(8000)
        ->and($week['sessions'])->toBe(2);
});

test('monthly mode aggregates into calendar month buckets', function () {
    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 2000,
        endedAt: '2026-08-15 10:00:00',
    );
    makeCompletedSession(
        stationId: $this->station2->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 4000,
        endedAt: '2026-09-02 10:00:00',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_MONTHLY,
        CarbonImmutable::parse('2026-08-01')->startOfDay(),
        CarbonImmutable::parse('2026-09-30')->endOfDay(),
        Filters::fromArray([]),
    );

    expect($report['totals']['final_lyd'])->toBe(6000);

    $keys = collect($report['buckets'])->pluck('key')->all();
    expect($keys)->toContain('2026-08')
        ->and($keys)->toContain('2026-09');

    $aug = collect($report['buckets'])->firstWhere('key', '2026-08');
    $sep = collect($report['buckets'])->firstWhere('key', '2026-09');

    expect($aug['final_lyd'])->toBe(2000)
        ->and($sep['final_lyd'])->toBe(4000);
});

test('station filter excludes other stations', function () {
    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 6000,
        endedAt: '2026-09-10 12:00:00',
    );
    makeCompletedSession(
        stationId: $this->station2->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 3000,
        endedAt: '2026-09-10 13:00:00',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-10')->endOfDay(),
        Filters::fromArray(['station_ids' => [$this->station1->id]]),
    );

    expect($report['totals']['final_lyd'])->toBe(6000)
        ->and($report['totals']['sessions'])->toBe(1);
});

test('cashier filter excludes other cashiers', function () {
    $other = User::factory()->create(['role' => 'cashier']);

    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 6000,
        endedAt: '2026-09-10 12:00:00',
    );
    makeCompletedSession(
        stationId: $this->station2->id,
        cashierId: $other->id,
        tierId: $this->tier->id,
        finalLyd: 3000,
        endedAt: '2026-09-10 13:00:00',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-10')->endOfDay(),
        Filters::fromArray(['cashier_ids' => [$this->cashier->id]]),
    );

    expect($report['totals']['final_lyd'])->toBe(6000)
        ->and($report['totals']['sessions'])->toBe(1);
});

test('session_type filter excludes non-matching types', function () {
    GameSession::create([
        'station_id' => $this->station1->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'prepaid',
        'status' => 'completed',
        'started_at' => '2026-09-10 11:00:00',
        'ended_at' => '2026-09-10 12:00:00',
        'final_total_millimes' => 4000,
        'time_amount_millimes' => 4000,
        'payment_status' => 'paid',
        'payment_method' => 'cash',
    ]);
    GameSessionInterval::create([
        'game_session_id' => GameSession::latest()->first()->id,
        'pricing_tier_id' => $this->tier->id,
        'started_at' => '2026-09-10 11:00:00',
        'ended_at' => '2026-09-10 12:00:00',
        'duration_seconds' => 3600,
        'billable_minutes' => 60,
        'rate_per_hour_millimes' => 4000,
        'station_multiplier' => 1.00,
        'subtotal_millimes' => 4000,
    ]);

    makeCompletedSession(
        stationId: $this->station2->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 7000,
        endedAt: '2026-09-10 13:00:00',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-10')->endOfDay(),
        Filters::fromArray(['session_types' => ['postpaid']]),
    );

    expect($report['totals']['sessions'])->toBe(1)
        ->and($report['totals']['final_lyd'])->toBe(7000);
});

test('payment_method filter excludes non-matching methods', function () {
    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 6000,
        endedAt: '2026-09-10 12:00:00',
        paymentMethod: 'cash',
    );
    makeCompletedSession(
        stationId: $this->station2->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 3000,
        endedAt: '2026-09-10 13:00:00',
        paymentMethod: 'card',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-10')->endOfDay(),
        Filters::fromArray(['payment_methods' => ['cash']]),
    );

    expect($report['totals']['final_lyd'])->toBe(6000)
        ->and($report['totals']['cash_lyd'])->toBe(6000)
        ->and($report['totals']['card_lyd'])->toBe(0);
});

test('non-completed sessions are excluded from revenue', function () {
    GameSession::create([
        'station_id' => $this->station1->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'active',
        'started_at' => '2026-09-10 11:00:00',
        'final_total_millimes' => 0,
        'payment_status' => 'unpaid',
    ]);

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-10')->endOfDay(),
        Filters::fromArray([]),
    );

    expect($report['totals']['sessions'])->toBe(0);
});

test('discounts reduce the bucket final total', function () {
    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 5000,
        endedAt: '2026-09-10 12:00:00',
        discountLyd: 1000,
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-10')->endOfDay(),
        Filters::fromArray([]),
    );

    expect($report['totals']['final_lyd'])->toBe(5000)
        ->and($report['totals']['discount_lyd'])->toBe(1000);
});

test('timezone shift moves a session into the correct local day', function () {
    // UTC 22:30 on 2026-09-10 = Africa/Tripoli 00:30 on 2026-09-11
    $session = GameSession::create([
        'station_id' => $this->station1->id,
        'cashier_id' => $this->cashier->id,
        'session_type' => 'postpaid',
        'status' => 'completed',
        'started_at' => '2026-09-10 21:00:00',
        'ended_at' => '2026-09-10 22:30:00',
        'final_total_millimes' => 6000,
        'time_amount_millimes' => 6000,
        'payment_status' => 'paid',
        'payment_method' => 'cash',
    ]);
    GameSessionInterval::create([
        'game_session_id' => $session->id,
        'pricing_tier_id' => $this->tier->id,
        'started_at' => '2026-09-10 21:00:00',
        'ended_at' => '2026-09-10 22:30:00',
        'duration_seconds' => 5400,
        'billable_minutes' => 90,
        'rate_per_hour_millimes' => 6000,
        'station_multiplier' => 1.00,
        'subtotal_millimes' => 9000,
    ]);

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-11')->endOfDay(),
        Filters::fromArray([]),
    );

    // Session ended at 22:30 UTC, which is 00:30 local on Sep 11 — should land on Sep 11 bucket
    $sep11 = collect($report['buckets'])->firstWhere('key', '2026-09-11');
    expect($sep11)->not->toBeNull()
        ->and($sep11['final_lyd'])->toBe(6000)
        ->and($report['buckets'][0]['final_lyd'])->toBe(0); // Sep 10 has nothing
});

test('station breakdown contains per-station revenue', function () {
    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 6000,
        endedAt: '2026-09-10 12:00:00',
    );
    makeCompletedSession(
        stationId: $this->station2->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 3000,
        endedAt: '2026-09-10 13:00:00',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-10')->endOfDay(),
        Filters::fromArray([]),
    );

    $bucket = $report['buckets'][0];
    expect(count($bucket['station_breakdown']))->toBe(2);

    $byStation = collect($bucket['station_breakdown'])->keyBy('station_id');
    expect($byStation[$this->station1->id]['final_lyd'])->toBe(6000)
        ->and($byStation[$this->station2->id]['final_lyd'])->toBe(3000);
});

test('toCsv produces a CSV with header and one row per bucket', function () {
    makeCompletedSession(
        stationId: $this->station1->id,
        cashierId: $this->cashier->id,
        tierId: $this->tier->id,
        finalLyd: 6000,
        endedAt: '2026-09-10 12:00:00',
    );

    $report = $this->aggregator->aggregate(
        RevenueAggregator::MODE_DAILY,
        CarbonImmutable::parse('2026-09-10')->startOfDay(),
        CarbonImmutable::parse('2026-09-10')->endOfDay(),
        Filters::fromArray([]),
    );

    $buckets = [];
    foreach ($report['buckets'] as $row) {
        $b = new Bucket(
            key: $row['key'],
            label: $row['label'],
            startsAt: CarbonImmutable::parse($row['starts_at']),
            endsAt: CarbonImmutable::parse($row['ends_at']),
            timeLyd: $row['time_lyd'],
            retailLyd: $row['retail_lyd'],
            discountLyd: $row['discount_lyd'],
            finalLyd: $row['final_lyd'],
            cashLyd: $row['cash_lyd'],
            cardLyd: $row['card_lyd'],
            sessions: $row['sessions'],
            minutes: $row['minutes'],
        );
        $buckets[$b->key] = $b;
    }

    $csv = $this->aggregator->toCsv($buckets);

    expect($csv)->toContain('Bucket,Sessions')
        ->and($csv)->toContain('2026-09-10,1,6.000,0.000');
});
