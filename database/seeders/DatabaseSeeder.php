<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use App\Models\PricingTier;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Default Users (Admin & Cashier)
        $admin = User::firstOrCreate(
            ['email' => 'admin@joygames.ly'],
            [
                'name' => 'Admin Manager',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'pin_code' => '9999',
                'is_active' => true,
            ]
        );

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@joygames.ly'],
            [
                'name' => 'Cashier Nick',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'pin_code' => '1234',
                'is_active' => true,
            ]
        );

        // 2. Create Pricing Rules (Libyan Dinar)
        PricingRule::firstOrCreate([], [
            'grace_period_minutes' => 3,
            'minimum_charge_minutes' => 15,
            'rounding_step_minutes' => 5,
            'vip_multiplier' => 1.50,
            'rogue_auto_sleep_seconds' => 120, // 2 minutes auto-cutoff safeguard
            'currency_code' => 'LYD',
            'currency_symbol' => 'د.ل',
            'allow_overtime_default' => true,
        ]);

        // 3. Create Controller Pricing Tiers
        PricingTier::firstOrCreate(
            ['controller_count_min' => 1, 'controller_count_max' => 2],
            [
                'name' => '1–2 Players (Solo / Duo)',
                'hourly_rate_millimes' => 6000, // 6.000 LYD / hr
                'display_order' => 1,
                'is_active' => true,
            ]
        );

        PricingTier::firstOrCreate(
            ['controller_count_min' => 3, 'controller_count_max' => 4],
            [
                'name' => '3–4 Players (Group Squad)',
                'hourly_rate_millimes' => 10000, // 10.000 LYD / hr
                'display_order' => 2,
                'is_active' => true,
            ]
        );

        // 4. Create 8 Lounge Stations with Smart TV IP Parameters (Hisense VIDAA, Android, Roku, Simulated)
        $stationsData = [
            ['name' => 'Station 01 (VIP)', 'station_number' => 1, 'type' => 'vip', 'tv_ip_address' => '192.168.1.101', 'tv_mac_address' => '00:11:22:33:44:01', 'tv_os_type' => 'vidaa'],
            ['name' => 'Station 02 (VIP)', 'station_number' => 2, 'type' => 'vip', 'tv_ip_address' => '192.168.1.102', 'tv_mac_address' => '00:11:22:33:44:02', 'tv_os_type' => 'vidaa'],
            ['name' => 'Station 03', 'station_number' => 3, 'type' => 'standard', 'tv_ip_address' => '192.168.1.103', 'tv_mac_address' => '00:11:22:33:44:03', 'tv_os_type' => 'vidaa'],
            ['name' => 'Station 04', 'station_number' => 4, 'type' => 'standard', 'tv_ip_address' => '192.168.1.104', 'tv_mac_address' => '00:11:22:33:44:04', 'tv_os_type' => 'android'],
            ['name' => 'Station 05', 'station_number' => 5, 'type' => 'standard', 'tv_ip_address' => '192.168.1.105', 'tv_mac_address' => '00:11:22:33:44:05', 'tv_os_type' => 'android'],
            ['name' => 'Station 06', 'station_number' => 6, 'type' => 'standard', 'tv_ip_address' => '192.168.1.106', 'tv_mac_address' => '00:11:22:33:44:06', 'tv_os_type' => 'roku'],
            ['name' => 'Station 07', 'station_number' => 7, 'type' => 'standard', 'tv_ip_address' => '192.168.1.107', 'tv_mac_address' => '00:11:22:33:44:07', 'tv_os_type' => 'simulated'],
            ['name' => 'Station 08', 'station_number' => 8, 'type' => 'standard', 'tv_ip_address' => '192.168.1.108', 'tv_mac_address' => '00:11:22:33:44:08', 'tv_os_type' => 'simulated'],
        ];

        foreach ($stationsData as $data) {
            Station::firstOrCreate(
                ['station_number' => $data['station_number']],
                array_merge($data, [
                    'tv_physical_state' => 'standby',
                    'current_state' => 'available',
                    'consecutive_on_pings' => 0,
                    'is_active' => true,
                ])
            );
        }

        // 5. Create Retail Snacks & Beverages (Prices in Millimes)
        $products = [
            ['name' => 'Red Bull Energy 250ml', 'category' => 'beverage', 'price_millimes' => 4500, 'stock_quantity' => 48],
            ['name' => 'Monster Energy 355ml', 'category' => 'beverage', 'price_millimes' => 5000, 'stock_quantity' => 36],
            ['name' => 'Coca-Cola 330ml Can', 'category' => 'beverage', 'price_millimes' => 2000, 'stock_quantity' => 72],
            ['name' => 'Mineral Water 500ml', 'category' => 'beverage', 'price_millimes' => 1000, 'stock_quantity' => 100],
            ['name' => 'Doritos Nacho Cheese 75g', 'category' => 'snack', 'price_millimes' => 3000, 'stock_quantity' => 40],
            ['name' => 'Pringles Sour Cream 110g', 'category' => 'snack', 'price_millimes' => 6000, 'stock_quantity' => 30],
            ['name' => 'KitKat 4 Finger Chocolate', 'category' => 'snack', 'price_millimes' => 2500, 'stock_quantity' => 50],
            ['name' => 'Snickers Bar 50g', 'category' => 'snack', 'price_millimes' => 2500, 'stock_quantity' => 50],
        ];

        foreach ($products as $prod) {
            Product::firstOrCreate(['name' => $prod['name']], $prod);
        }

        // 6. Open Initial Active Shift for Cashier
        if (! Shift::where('status', 'open')->exists()) {
            Shift::create([
                'user_id' => $cashier->id,
                'started_at' => now(),
                'opening_float_millimes' => 150000, // 150.000 LYD
                'status' => 'open',
                'notes' => 'Morning shift open',
            ]);
        }
    }
}
