<?php

namespace App\Console\Commands;

use App\Models\Station;
use App\Services\Tv\TvReconciliationService;
use Illuminate\Console\Command;

class ReconcileTvsCommand extends Command
{
    protected $signature = 'tvs:reconcile {--station= : Reconcile a single station id}';

    protected $description = 'Poll all Smart TVs and reconcile physical power state against expected operational state.';

    public function handle(TvReconciliationService $reconciliation): int
    {
        $start = microtime(true);

        if ($stationId = $this->option('station')) {
            $station = Station::find($stationId);
            if (! $station) {
                $this->error("Station #{$stationId} not found.");

                return self::FAILURE;
            }
            $results = [$station->id => $reconciliation->reconcileStation($station)];
        } else {
            $results = $reconciliation->reconcileAll();
        }

        $rogues = collect($results)->where('is_rogue', true);
        $autoPaused = collect($results)->where('action_taken', 'auto_paused_session');
        $autoBlackout = collect($results)->where('action_taken', 'auto_blackout_enforced');

        $this->info(sprintf(
            'Reconciled %d stations in %.2fms — Rogue: %d, Auto-Paused: %d, Auto-Blackout: %d',
            count($results),
            (microtime(true) - $start) * 1000,
            $rogues->count(),
            $autoPaused->count(),
            $autoBlackout->count(),
        ));

        return self::SUCCESS;
    }
}
