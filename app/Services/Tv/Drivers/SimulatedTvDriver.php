<?php

namespace App\Services\Tv\Drivers;

use App\Models\Station;
use App\Models\StationAudit;
use App\Services\Tv\Contracts\TvDeviceDriverInterface;
use App\Services\Tv\TvStatus;
use Illuminate\Support\Facades\Log;

class SimulatedTvDriver implements TvDeviceDriverInterface
{
    public function turnOn(Station $station): bool
    {
        Log::info("SimulatedTvDriver: Turning ON TV Screen for Station #{$station->station_number} ({$station->name})");

        $station->update([
            'tv_physical_state' => 'screen_on',
            'last_ping_at' => now(),
        ]);

        StationAudit::create([
            'station_id' => $station->id,
            'game_session_id' => $station->activeSession?->id,
            'event_type' => 'tv_wake_sent',
            'physical_state' => 'screen_on',
            'expected_state' => $station->current_state,
            'details' => ['driver' => 'simulated', 'action' => 'turn_on_wol'],
        ]);

        return true;
    }

    public function turnOff(Station $station): bool
    {
        Log::info("SimulatedTvDriver: Turning OFF TV Screen for Station #{$station->station_number} ({$station->name})");

        $station->update([
            'tv_physical_state' => 'standby',
            'last_ping_at' => now(),
        ]);

        StationAudit::create([
            'station_id' => $station->id,
            'game_session_id' => $station->activeSession?->id,
            'event_type' => 'manual_tv_off',
            'physical_state' => 'standby',
            'expected_state' => $station->current_state,
            'details' => ['driver' => 'simulated', 'action' => 'turn_off_standby'],
        ]);

        return true;
    }

    public function pollStatus(Station $station): TvStatus
    {
        return match ($station->tv_physical_state) {
            'screen_on' => TvStatus::SCREEN_ON,
            'standby' => TvStatus::STANDBY,
            default => TvStatus::UNREACHABLE,
        };
    }
}
