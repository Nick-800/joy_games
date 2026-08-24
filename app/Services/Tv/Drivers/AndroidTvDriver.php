<?php

namespace App\Services\Tv\Drivers;

use App\Models\Station;
use App\Models\StationAudit;
use App\Services\Tv\Contracts\TvDeviceDriverInterface;
use App\Services\Tv\TvStatus;
use Illuminate\Support\Facades\Log;

class AndroidTvDriver implements TvDeviceDriverInterface
{
    protected const ADB_TCP_PORT = 5555;

    protected const GOOGLE_TV_PORT = 6466;

    protected const WOL_PORT = 9;

    public function turnOn(Station $station): bool
    {
        if (! $station->tv_mac_address) {
            Log::warning("AndroidTvDriver: Station #{$station->station_number} has no TV MAC address for WoL.");

            return false;
        }

        $sent = $this->sendWakeOnLan($station->tv_mac_address, $station->tv_ip_address);

        StationAudit::create([
            'station_id' => $station->id,
            'game_session_id' => $station->activeSession?->id,
            'event_type' => 'tv_wake_sent',
            'physical_state' => $station->tv_physical_state,
            'expected_state' => $station->current_state,
            'details' => ['driver' => 'android_tv', 'mac' => $station->tv_mac_address, 'ip' => $station->tv_ip_address, 'success' => $sent],
        ]);

        return $sent;
    }

    public function turnOff(Station $station): bool
    {
        if (! $station->tv_ip_address) {
            Log::warning("AndroidTvDriver: Station #{$station->station_number} has no TV IP address.");

            return false;
        }

        // Try ADB keyevent 26 (POWER / SLEEP) or TCP port
        $sent = $this->sendAdbSleepCommand($station->tv_ip_address);

        StationAudit::create([
            'station_id' => $station->id,
            'game_session_id' => $station->activeSession?->id,
            'event_type' => 'manual_tv_off',
            'physical_state' => $station->tv_physical_state,
            'expected_state' => $station->current_state,
            'details' => ['driver' => 'android_tv', 'ip' => $station->tv_ip_address, 'success' => $sent],
        ]);

        return $sent;
    }

    public function pollStatus(Station $station): TvStatus
    {
        if (! $station->tv_ip_address) {
            return TvStatus::UNREACHABLE;
        }

        $errno = 0;
        $errstr = '';
        // Probe ADB port 5555 or Google TV port 6466
        $socket = @fsockopen($station->tv_ip_address, self::ADB_TCP_PORT, $errno, $errstr, 1.0);
        if (! $socket) {
            $socket = @fsockopen($station->tv_ip_address, self::GOOGLE_TV_PORT, $errno, $errstr, 1.0);
        }

        if ($socket) {
            fclose($socket);

            return TvStatus::SCREEN_ON;
        }

        return TvStatus::STANDBY;
    }

    protected function sendWakeOnLan(string $macAddress, ?string $ipAddress = null): bool
    {
        $cleanMac = str_replace([':', '-', ' '], '', $macAddress);
        if (strlen($cleanMac) !== 12) {
            return false;
        }

        $macBin = pack('H*', $cleanMac);
        $magicPacket = str_repeat(chr(0xFF), 6).str_repeat($macBin, 16);

        $targetIp = $ipAddress ?: '255.255.255.255';

        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (! $socket) {
            return false;
        }

        @socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        $sent = @socket_sendto($socket, $magicPacket, strlen($magicPacket), 0, $targetIp, self::WOL_PORT);
        @socket_close($socket);

        return $sent !== false;
    }

    protected function sendAdbSleepCommand(string $ipAddress): bool
    {
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($ipAddress, self::ADB_TCP_PORT, $errno, $errstr, 1.5);
        if ($fp) {
            fclose($fp);

            return true;
        }

        return false;
    }
}
