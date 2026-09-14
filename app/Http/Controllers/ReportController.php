<?php

namespace App\Http\Controllers;

use App\Models\PricingRule;
use App\Models\Station;
use App\Models\User;
use App\Services\Reports\Filters;
use App\Services\Reports\RevenueAggregator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected RevenueAggregator $aggregator) {}

    public function index(Request $request): Response
    {
        $stations = Station::orderBy('station_number')->get(['id', 'name', 'station_number']);
        $cashiers = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reports', [
            'stations' => $stations,
            'cashiers' => $cashiers,
            'serverTime' => now()->toIso8601String(),
            'defaultRange' => [
                'from' => CarbonImmutable::now()->subDays(30)->toIso8601String(),
                'to' => CarbonImmutable::now()->toIso8601String(),
            ],
            'rule' => [
                'timezone' => PricingRule::current()?->timezone ?? 'UTC',
                'currency_symbol' => PricingRule::current()?->currency_symbol ?? 'LYD',
            ],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $payload = $this->buildReport($request);

        return response()->json($payload);
    }

    public function sessions(Request $request): JsonResponse
    {
        $mode = (string) $request->input('mode', RevenueAggregator::MODE_DAILY);
        $key = (string) $request->input('key');
        $filters = Filters::fromArray($request->all());

        abort_if($key === '', 422, 'A bucket key is required.');

        $sessions = $this->aggregator->sessionsForBucket($mode, $key, $filters);

        return response()->json(['sessions' => $sessions]);
    }

    public function export(Request $request): StreamedResponse
    {
        $payload = $this->buildReport($request);

        $filename = sprintf(
            'reports_%s_%s_%s.csv',
            $payload['mode'],
            str_replace('-', '', $payload['from']),
            str_replace('-', '', $payload['to']),
        );

        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Bucket', 'Sessions', 'Time LYD', 'Discount LYD',
                'Final LYD', 'Cash LYD', 'Minutes',
            ]);

            foreach ($payload['buckets'] as $row) {
                fputcsv($out, [
                    $row['label'],
                    (string) $row['sessions'],
                    number_format($row['time_lyd'] / 1000, 3, '.', ''),
                    number_format($row['discount_lyd'] / 1000, 3, '.', ''),
                    number_format($row['final_lyd'] / 1000, 3, '.', ''),
                    number_format($row['cash_lyd'] / 1000, 3, '.', ''),
                    (string) $row['minutes'],
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildReport(Request $request): array
    {
        $mode = (string) $request->input('mode', RevenueAggregator::MODE_DAILY);
        $from = $request->input('from') ? CarbonImmutable::parse($request->input('from')) : CarbonImmutable::now()->subDays(30);
        $to = $request->input('to') ? CarbonImmutable::parse($request->input('to')) : CarbonImmutable::now();
        $excludeEmpty = ! $request->boolean('include_empty', false);

        return $this->aggregator->aggregate($mode, $from, $to, Filters::fromArray($request->all()), $excludeEmpty);
    }
}
