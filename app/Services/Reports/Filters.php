<?php

namespace App\Services\Reports;

class Filters
{
    /**
     * @param  array<int, int>  $stationIds
     * @param  array<int, int>  $cashierIds
     * @param  array<int, string>  $paymentMethods
     * @param  array<int, string>  $sessionTypes
     */
    public function __construct(
        public array $stationIds = [],
        public array $cashierIds = [],
        public array $paymentMethods = [],
        public array $sessionTypes = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            stationIds: array_values(array_filter(array_map('intval', (array) ($data['station_ids'] ?? [])))),
            cashierIds: array_values(array_filter(array_map('intval', (array) ($data['cashier_ids'] ?? [])))),
            paymentMethods: array_values(array_filter((array) ($data['payment_methods'] ?? []), fn ($v) => in_array($v, ['cash', 'card', 'split'], true))),
            sessionTypes: array_values(array_filter((array) ($data['session_types'] ?? []), fn ($v) => in_array($v, ['prepaid', 'postpaid'], true))),
        );
    }

    public function isEmpty(): bool
    {
        return $this->stationIds === [] && $this->cashierIds === [] && $this->paymentMethods === [] && $this->sessionTypes === [];
    }

    public function applyToQuery($query): void
    {
        if ($this->stationIds !== []) {
            $query->whereIn('station_id', $this->stationIds);
        }
        if ($this->cashierIds !== []) {
            $query->whereIn('cashier_id', $this->cashierIds);
        }
        if ($this->paymentMethods !== []) {
            $query->whereIn('payment_method', $this->paymentMethods);
        }
        if ($this->sessionTypes !== []) {
            $query->whereIn('session_type', $this->sessionTypes);
        }
    }
}
