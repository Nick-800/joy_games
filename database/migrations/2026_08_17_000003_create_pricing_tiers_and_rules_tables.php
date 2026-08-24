<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('controller_count_min')->default(1);
            $table->unsignedInteger('controller_count_max')->default(2);
            $table->unsignedInteger('hourly_rate_millimes')->default(6000); // 6.000 LYD
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('grace_period_minutes')->default(3);
            $table->unsignedInteger('minimum_charge_minutes')->default(15);
            $table->unsignedInteger('rounding_step_minutes')->default(5);
            $table->decimal('vip_multiplier', 4, 2)->default(1.50);
            $table->unsignedInteger('rogue_auto_sleep_seconds')->default(180);
            $table->string('currency_code', 10)->default('LYD');
            $table->string('currency_symbol', 10)->default('د.ل');
            $table->boolean('allow_overtime_default')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('pricing_tiers');
    }
};
