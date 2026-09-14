<?php

namespace App\Services\Reports;

use App\Models\GameSession;
use App\Models\PricingRule;
use Carbon\CarbonImmutable;

class RevenueAggregator
{
    public const MODE_DAILY = 'daily';

    public const MODE_WEEKLY = 'weekly';

    public const MODE_MONTHLY = 'monthly';

    public static function modes(): array
    {
        return [self::MODE_DAILY, self::MODE_WEEKLY, self::MODE_MONTHLY];
    }

    /**
     * @return array{mode:string, from:string, to:string, timezone:string, filters:array<string, mixed>, totals:array<string, int>, buckets:array<int, array<string, mixed>>}
     */
    public function aggregate(string $mode, CarbonImmutable $from, CarbonImmutable $to, Filters $filters): array
    {
        if (! in_array($mode, self::modes(), true)) {
            throw new \InvalidArgumentException("Unsupported report mode [{$mode}].");
        }

        $rule = PricingRule::current();
        $tz = $rule?->timezone ?: 'UTC';
        $localFrom = $from->setTimezone($tz)->startOfDay();
        $localTo = $to->setTimezone($tz)->endOfDay();

        if ($localFrom->greaterThan($localTo)) {
            $localFrom = $localTo->copy()->startOfDay();
        }

        $buckets = $this->buildEmptyBuckets($mode, $localFrom, $localTo);

        $query = GameSession::query()
            ->with(['station', 'intervals'])
            ->where('status', 'completed')
            ->where('payment_status', 'paid');

        $filters->applyToQuery($query);

        $query->whereBetween('ended_at', [$localFrom, $localTo]);

        $sessions = $query->get();

        foreach ($sessions as $session) {
            $key = $this->bucketKeyFor($mode, CarbonImmutable::instance($session->ended_at)->setTimezone($tz));
            if (! isset($buckets[$key])) {
                continue;
            }
            $this->accumulate($buckets[$key], $session);
        }

        $totals = $this->totalsFromBuckets($buckets);

        return [
            'mode' => $mode,
            'from' => $localFrom->toIso8601String(),
            'to' => $localTo->toIso8601String(),
            'timezone' => $tz,
            'filters' => [
                'station_ids' => $filters->stationIds,
                'cashier_ids' => $filters->cashierIds,
                'payment_methods' => $filters->paymentMethods,
                'session_types' => $filters->sessionTypes,
            ],
            'totals' => $totals,
            'buckets' => array_values(array_map(fn (Bucket $b) => $b->toArray(), $buckets)),
        ];
    }

    /**
     * Sessions contributing to a given bucket key.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sessionsForBucket(string $mode, string $key, Filters $filters): array
    {
        $rule = PricingRule::current();
        $tz = $rule?->timezone ?: 'UTC';
        [$from, $to] = $this->bucketDateRange($mode, $key, $tz);

        $query = GameSession::query()
            ->with(['station', 'cashier', 'intervals.pricingTier'])
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('ended_at', [$from, $to]);

        $filters->applyToQuery($query);

        return $query
            ->orderBy('ended_at')
            ->get()
            ->map(fn (GameSession $session) => [
                'id' => $session->id,
                'station_id' => $session->station_id,
                'station_name' => $session->station?->name,
                'cashier_name' => $session->cashier?->name,
                'session_type' => $session->session_type,
                'started_at' => $session->started_at?->toIso8601String(),
                'ended_at' => $session->ended_at?->toIso8601String(),
                'payment_method' => $session->payment_method,
                'final_total_millimes' => $session->final_total_millimes,
                'final_total_lyd' => $session->final_total_millimes / 1000,
                'previous_final_total_millimes' => $session->previous_final_total_millimes,
                'recomputed_delta_millimes' => $session->recomputedDeltaMillimes(),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function bucketHeaders(): array
    {
        return [
            'Bucket', 'Sessions', 'Time LYD', 'Retail LYD', 'Discount LYD',
            'Final LYD', 'Cash LYD', 'Card LYD', 'Minutes',
        ];
    }

    /**
     * @param  array<string, Bucket>  $buckets
     */
    public function toCsv(array $buckets): string
    {
        $rows = [];
        $rows[] = $this->bucketHeaders();
        foreach ($buckets as $bucket) {
            $rows[] = [
                $bucket->label,
                (string) $bucket->sessions,
                number_format($bucket->timeLyd / 1000, 3, '.', ''),
                number_format($bucket->retailLyd / 1000, 3, '.', ''),
                number_format($bucket->discountLyd / 1000, 3, '.', ''),
                number_format($bucket->finalLyd / 1000, 3, '.', ''),
                number_format($bucket->cashLyd / 1000, 3, '.', ''),
                number_format($bucket->cardLyd / 1000, 3, '.', ''),
                (string) $bucket->minutes,
            ];
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * @return array<string, Bucket>
     */
    protected function buildEmptyBuckets(string $mode, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $buckets = [];
        $cursor = $from->copy();

        while (! $cursor->greaterThan($to)) {
            $startsAt = match ($mode) {
                self::MODE_DAILY => $cursor->startOfDay(),
                self::MODE_WEEKLY => $cursor->startOfWeek(CarbonImmutable::MONDAY),
                self::MODE_MONTHLY => $cursor->startOfMonth(),
            };
            $endsAt = match ($mode) {
                self::MODE_DAILY => $startsAt->endOfDay(),
                self::MODE_WEEKLY => $startsAt->endOfWeek(CarbonImmutable::MONDAY),
                self::MODE_MONTHLY => $startsAt->endOfMonth(),
            };

            $key = $this->bucketKeyFor($mode, $startsAt);
            $label = match ($mode) {
                self::MODE_DAILY => $startsAt->format('Y-m-d'),
                self::MODE_WEEKLY => 'Week '.$startsAt->format('W / Y'),
                self::MODE_MONTHLY => $startsAt->format('Y-m'),
            };

            if (! isset($buckets[$key])) {
                $buckets[$key] = new Bucket(
                    key: $key,
                    label: $label,
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                );
            }

            $cursor = match ($mode) {
                self::MODE_DAILY => $cursor->addDay(),
                self::MODE_WEEKLY => $cursor->addWeek(),
                self::MODE_MONTHLY => $cursor->addMonth(),
            };

            if (count($buckets) > 366) {
                break;
            }
        }

        return $buckets;
    }

    protected function bucketKeyFor(string $mode, CarbonImmutable $when): string
    {
        $local = $when->copy();

        return match ($mode) {
            self::MODE_DAILY => $local->startOfDay()->format('Y-m-d'),
            self::MODE_WEEKLY => $local->startOfWeek(CarbonImmutable::MONDAY)->format('Y-\\WW'),
            self::MODE_MONTHLY => $local->startOfMonth()->format('Y-m'),
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function bucketDateRange(string $mode, string $key, string $tz): array
    {
        $startsAt = match ($mode) {
            self::MODE_DAILY => CarbonImmutable::createFromFormat('Y-m-d', $key, $tz)->startOfDay(),
            self::MODE_WEEKLY => CarbonImmutable::createFromFormat('Y-\WW', $key, $tz)->startOfWeek(CarbonImmutable::MONDAY),
            self::MODE_MONTHLY => CarbonImmutable::createFromFormat('Y-m', $key, $tz)->startOfMonth(),
        };
        $endsAt = match ($mode) {
            self::MODE_DAILY => $startsAt->endOfDay(),
            self::MODE_WEEKLY => $startsAt->endOfWeek(CarbonImmutable::MONDAY),
            self::MODE_MONTHLY => $startsAt->endOfMonth(),
        };

        return [$startsAt, $endsAt];
    }

    protected function accumulate(Bucket $bucket, GameSession $session): void
    {
        $bucket->timeLyd += $session->time_amount_millimes;
        $bucket->retailLyd += $session->retail_amount_millimes;
        $bucket->discountLyd += $session->discount_amount_millimes;
        $bucket->finalLyd += $session->final_total_millimes;
        $bucket->sessions++;

        if ($session->payment_method === 'cash') {
            $bucket->cashLyd += $session->final_total_millimes;
        } elseif ($session->payment_method === 'card') {
            $bucket->cardLyd += $session->final_total_millimes;
        }

        $minutes = (int) $session->intervals->sum('billable_minutes');
        $bucket->minutes += $minutes;

        if ($session->isPrepaid()) {
            $bucket->prepaidSessions++;
        } elseif ($session->isPostpaid()) {
            $bucket->postpaidSessions++;
        }

        if ($session->station?->isVip()) {
            $bucket->vipSessions++;
        }

        $stationKey = $session->station_id;
        $existing = $bucket->stationBreakdown->get($stationKey);
        if (! $existing) {
            $existing = [
                'station_id' => $session->station_id,
                'station_name' => $session->station?->name ?? 'Station',
                'time_lyd' => 0,
                'retail_lyd' => 0,
                'discount_lyd' => 0,
                'final_lyd' => 0,
                'cash_lyd' => 0,
                'card_lyd' => 0,
                'sessions' => 0,
                'minutes' => 0,
            ];
        }

        $existing['time_lyd'] += $session->time_amount_millimes;
        $existing['retail_lyd'] += $session->retail_amount_millimes;
        $existing['discount_lyd'] += $session->discount_amount_millimes;
        $existing['final_lyd'] += $session->final_total_millimes;
        $existing['sessions']++;
        $existing['minutes'] += $minutes;

        if ($session->payment_method === 'cash') {
            $existing['cash_lyd'] += $session->final_total_millimes;
        } elseif ($session->payment_method === 'card') {
            $existing['card_lyd'] += $session->final_total_millimes;
        }

        $bucket->stationBreakdown->put($stationKey, $existing);
    }

    /**
     * @param  array<string, Bucket>  $buckets
     * @return array<string, int>
     */
    protected function totalsFromBuckets(array $buckets): array
    {
        $totals = [
            'time_lyd' => 0,
            'retail_lyd' => 0,
            'discount_lyd' => 0,
            'final_lyd' => 0,
            'cash_lyd' => 0,
            'card_lyd' => 0,
            'sessions' => 0,
            'minutes' => 0,
            'prepaid_sessions' => 0,
            'postpaid_sessions' => 0,
            'vip_sessions' => 0,
        ];

        foreach ($buckets as $bucket) {
            $totals['time_lyd'] += $bucket->timeLyd;
            $totals['retail_lyd'] += $bucket->retailLyd;
            $totals['discount_lyd'] += $bucket->discountLyd;
            $totals['final_lyd'] += $bucket->finalLyd;
            $totals['cash_lyd'] += $bucket->cashLyd;
            $totals['card_lyd'] += $bucket->cardLyd;
            $totals['sessions'] += $bucket->sessions;
            $totals['minutes'] += $bucket->minutes;
            $totals['prepaid_sessions'] += $bucket->prepaidSessions;
            $totals['postpaid_sessions'] += $bucket->postpaidSessions;
            $totals['vip_sessions'] += $bucket->vipSessions;
        }

        return $totals;
    }
}
