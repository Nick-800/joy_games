<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * @phpstan-type StationBreakdownArray array{station_id:int, station_name:string, time_lyd:int, retail_lyd:int, discount_lyd:int, final_lyd:int, cash_lyd:int, card_lyd:int, sessions:int, minutes:int}
 * @phpstan-type BucketArray array{key:string, label:string, starts_at:string, ends_at:string, time_lyd:int, retail_lyd:int, discount_lyd:int, final_lyd:int, cash_lyd:int, card_lyd:int, sessions:int, minutes:int, prepaid_sessions:int, postpaid_sessions:int, vip_sessions:int, station_breakdown:array<int, StationBreakdownArray>}
 */
class Bucket
{
    /**
     * @param  Collection<int, array{station_id:int, station_name:string, time_lyd:int, retail_lyd:int, discount_lyd:int, final_lyd:int, cash_lyd:int, card_lyd:int, sessions:int, minutes:int}>  $stationBreakdown
     */
    public function __construct(
        public string $key,
        public string $label,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $timeLyd = 0,
        public int $retailLyd = 0,
        public int $discountLyd = 0,
        public int $finalLyd = 0,
        public int $cashLyd = 0,
        public int $cardLyd = 0,
        public int $sessions = 0,
        public int $minutes = 0,
        public int $prepaidSessions = 0,
        public int $postpaidSessions = 0,
        public int $vipSessions = 0,
        public Collection $stationBreakdown = new Collection,
    ) {}

    /**
     * @return BucketArray
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'time_lyd' => $this->timeLyd,
            'retail_lyd' => $this->retailLyd,
            'discount_lyd' => $this->discountLyd,
            'final_lyd' => $this->finalLyd,
            'cash_lyd' => $this->cashLyd,
            'card_lyd' => $this->cardLyd,
            'sessions' => $this->sessions,
            'minutes' => $this->minutes,
            'prepaid_sessions' => $this->prepaidSessions,
            'postpaid_sessions' => $this->postpaidSessions,
            'vip_sessions' => $this->vipSessions,
            'station_breakdown' => $this->stationBreakdown->values()->all(),
        ];
    }
}
