<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use App\Services\Billing\RateEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RecomputeSessionTotalsCommand extends Command
{
    protected $signature = 'sessions:recompute-totals
        {--dry-run : Report deltas only, do not write back to the database}
        {--chunk=100 : Process this many sessions per chunk}
        {--from= : Only recompute sessions ended after this ISO date (inclusive)}
        {--to= : Only recompute sessions ended before this ISO date (inclusive)}';

    protected $description = 'Backfill theoretical totals for completed sessions using the pause-aware RateEngine. Writes to previous_final_total_millimes and never overwrites final_total_millimes (cash-drawer integrity).';

    public function handle(RateEngine $rateEngine): int
    {
        $dry = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $from = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : null;

        $query = GameSession::query()->where('status', 'completed');

        if ($from) {
            $query->where('ended_at', '>=', $from);
        }
        if ($to) {
            $query->where('ended_at', '<=', $to);
        }

        $count = 0;
        $mismatches = 0;
        $totalDelta = 0;
        $maxAbsDelta = 0;

        $query->orderBy('id')->chunkById($chunk, function ($sessions) use ($rateEngine, $dry, &$count, &$mismatches, &$totalDelta, &$maxAbsDelta) {
            foreach ($sessions as $session) {
                $count++;
                $totals = $rateEngine->calculateSessionTotal($session, $session->ended_at);
                $theoretical = $totals['final_total_millimes'];
                $delta = $theoretical - $session->final_total_millimes;
                $absDelta = abs($delta);

                if ($absDelta > 0) {
                    $mismatches++;
                    $totalDelta += $delta;
                    $maxAbsDelta = max($maxAbsDelta, $absDelta);
                    $this->line(sprintf(
                        'Session #%d: billed=%d theoretical=%d delta=%+d',
                        $session->id,
                        $session->final_total_millimes,
                        $theoretical,
                        $delta,
                    ));
                }

                if (! $dry) {
                    $session->update([
                        'previous_final_total_millimes' => $theoretical,
                        'recomputed_at' => now(),
                    ]);
                }
            }
        });

        $this->info(sprintf(
            '%s%d sessions scanned, %d mismatches, total delta %+d millimes, max |delta| %d millimes.',
            $dry ? '[dry-run] ' : '',
            $count,
            $mismatches,
            $totalDelta,
            $maxAbsDelta,
        ));

        return self::SUCCESS;
    }
}
