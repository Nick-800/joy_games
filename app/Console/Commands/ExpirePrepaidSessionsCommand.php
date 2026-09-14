<?php

namespace App\Console\Commands;

use App\Models\GameSession;
use App\Models\PricingRule;
use App\Services\Sessions\SessionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ExpirePrepaidSessionsCommand extends Command
{
    protected $signature = 'sessions:expire-prepaid {--dry-run : Report only, do not end sessions}';

    protected $description = 'Auto-end prepaid sessions whose allocated_minutes have elapsed (or warn if allow_overtime).';

    public function handle(SessionManager $sessions): int
    {
        $now = Carbon::now();
        $dry = (bool) $this->option('dry-run');
        $rule = PricingRule::current();
        $overtimeGrace = $rule ? (int) $rule->prepaid_overtime_grace_minutes : 5;

        $candidates = GameSession::query()
            ->where('status', 'active')
            ->where('session_type', 'prepaid')
            ->whereNotNull('allocated_minutes')
            ->get();

        $ended = 0;

        foreach ($candidates as $session) {
            $elapsedSeconds = $session->started_at->diffInSeconds($now) - (int) $session->total_paused_seconds;
            $elapsedMinutes = (int) floor($elapsedSeconds / 60);

            if ($elapsedMinutes < (int) $session->allocated_minutes) {
                continue;
            }

            if ($session->allow_overtime && $elapsedMinutes <= ((int) $session->allocated_minutes) + $overtimeGrace) {
                continue;
            }

            $this->line(sprintf(
                'Session #%d on station #%d elapsed %dm / allocated %dm (overtime: %s)',
                $session->id,
                $session->station_id,
                $elapsedMinutes,
                $session->allocated_minutes,
                $session->allow_overtime ? 'yes' : 'no',
            ));

            if (! $dry) {
                $sessions->endSession($session);
                $ended++;
            }
        }

        $this->info(sprintf(
            '%s%d prepaid session(s) %s.',
            $dry ? '[dry-run] ' : '',
            $ended,
            $dry ? 'would be ended' : 'ended',
        ));

        return self::SUCCESS;
    }
}
