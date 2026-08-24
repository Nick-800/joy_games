<?php

namespace App\Services\Tv;

use App\Models\Station;
use App\Services\Tv\Contracts\TvDeviceDriverInterface;
use App\Services\Tv\Drivers\AndroidTvDriver;
use App\Services\Tv\Drivers\HisenseVidaaTvDriver;
use App\Services\Tv\Drivers\RokuTvDriver;
use App\Services\Tv\Drivers\SimulatedTvDriver;

class TvDriverFactory
{
    public static function make(Station $station): TvDeviceDriverInterface
    {
        return match ($station->tv_os_type) {
            'vidaa' => app(HisenseVidaaTvDriver::class),
            'android' => app(AndroidTvDriver::class),
            'roku' => app(RokuTvDriver::class),
            default => app(SimulatedTvDriver::class),
        };
    }
}
