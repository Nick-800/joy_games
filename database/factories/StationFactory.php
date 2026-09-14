<?php

namespace Database\Factories;

use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Station>
 */
class StationFactory extends Factory
{
    protected $model = Station::class;

    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 999);

        return [
            'name' => "Station {$number}",
            'station_number' => $number,
            'type' => fake()->randomElement(['standard', 'vip']),
            'tv_ip_address' => '192.168.1.'.fake()->numberBetween(2, 254),
            'tv_mac_address' => fake()->macAddress(),
            'tv_os_type' => fake()->randomElement(['vidaa', 'android', 'roku', 'simulated']),
            'tv_physical_state' => 'standby',
            'current_state' => 'available',
            'consecutive_on_pings' => 0,
            'is_active' => true,
        ];
    }

    public function available(): static
    {
        return $this->state(fn () => [
            'current_state' => 'available',
            'tv_physical_state' => 'standby',
        ]);
    }

    public function rogue(): static
    {
        return $this->state(fn () => [
            'current_state' => 'available',
            'tv_physical_state' => 'screen_on',
            'consecutive_on_pings' => 2,
            'first_detected_on_at' => now(),
        ]);
    }
}
