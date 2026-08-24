<?php

namespace App\Services\Tv;

enum TvStatus: string
{
    case STANDBY = 'standby';
    case SCREEN_ON = 'screen_on';
    case UNREACHABLE = 'unreachable';
}
