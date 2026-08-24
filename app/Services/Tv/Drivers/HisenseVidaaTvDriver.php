<?php

namespace App\Services\Tv\Drivers;

use App\Models\Station;
use App\Models\StationAudit;
use App\Services\Tv\Contracts\TvDeviceDriverInterface;
use App\Services\Tv\TvStatus;
use Illuminate\Support\Facades\Log;

class HisenseVidaaTvDriver implements TvDeviceDriverInterface
{
    protected const VIDAA_TCP_PORT = 36669;

    protected const WOL_PORT = 9;

    public function turnOn(Station $station): bool
    {
        if (! $station->tv_mac_address) {
            Log::warning("HisenseVidaaTvDriver: Station #{$station->station_number} has no TV MAC address for WoL.");

            return false;
        }

        $sent = $this->sendWakeOnLan($station->tv_mac_address, $station->tv_ip_address);

        StationAudit::create([
            'station_id' => $station->id,
            'game_session_id' => $station->activeSession?->id,
            'event_type' => 'tv_wake_sent',
            'physical_state' => $station->tv_physical_state,
            'expected_state' => $station->current_state,
            'details' => ['driver' => 'hisense_vidaa', 'mac' => $station->tv_mac_address, 'ip' => $station->tv_ip_address, 'success' => $sent],
        ]);

        return $sent;
    }

    public function turnOff(Station $station): bool
    {
        if (! $station->tv_ip_address) {
            Log::warning("HisenseVidaaTvDriver: Station #{$station->station_number} has no TV IP address.");

            return false;
        }

        $sent = $this->sendVidaaStandbyCommand($station->tv_ip_address, $station->tv_auth_token);

        StationAudit::create([
            'station_id' => $station->id,
            'game_session_id' => $station->activeSession?->id,
            'event_type' => 'manual_tv_off',
            'physical_state' => $station->tv_physical_state,
            'expected_state' => $station->current_state,
            'details' => ['driver' => 'hisense_vidaa', 'ip' => $station->tv_ip_address, 'success' => $sent],
        ]);

        return $sent;
    }

    public function pollStatus(Station $station): TvStatus
    {
        if (! $station->tv_ip_address) {
            return TvStatus::UNREACHABLE;
        }

        // Port 36669 probe on Hisense VIDAA OS
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($station->tv_ip_address, self::VIDAA_TCP_PORT, $errno, $errstr, 1.2);

        if ($socket) {
            fclose($socket);

            return TvStatus::SCREEN_ON;
        }

        return TvStatus::STANDBY;
    }

    /**
     * Send standard Wake-on-LAN UDP magic packet to TV MAC address.
     */
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

    /**
     * Send TV Standby command via TCP / MQTT socket to Hisense VIDAA TV on port 36669.
     */
    protected function sendVidaaStandbyCommand(string $ipAddress, ?string $authToken = null): bool
    {
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($ipAddress, self::VIDAA_TCP_PORT, $errno, $errstr, 2.0);

        if (! $fp) {
            return false;
        }

        // Hisense VIDAA JSON Key Command payload
        $payload = json_encode([
            'action' => 'key',
            'key' => 'KEY_STANDBY',
            'auth' => $authToken,
        ]);

        fwrite($fp, $payload."\r\n");
        fclose($fp);

        return true;
    }
}
