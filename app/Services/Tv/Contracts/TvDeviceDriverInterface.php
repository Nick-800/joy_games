<?php

namespace App\Services\Tv\Contracts;

use App\Models\Station;
use App\Services\Tv\TvStatus;

interface TvDeviceDriverInterface
{
    /**
     * Turn ON the TV display via Wake-on-LAN (UDP Port 9 to TV MAC) or IP power payload.
     */
    public function turnOn(Station $station): bool;

    /**
     * Turn OFF the TV display / trigger screen standby via native TV OS IP command.
     */
    public function turnOff(Station $station): bool;

    /**
     * Query the real-time physical power/screen state of the Smart TV.
     */
    public function pollStatus(Station $station): TvStatus;
}
