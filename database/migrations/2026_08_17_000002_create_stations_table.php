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
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('station_number')->unique();
            $table->string('type')->default('standard'); // standard, vip
            $table->string('tv_ip_address', 45)->nullable();
            $table->string('tv_mac_address', 50)->nullable();
            $table->string('tv_os_type')->default('simulated'); // vidaa, android, roku, simulated
            $table->string('tv_auth_token')->nullable();
            $table->string('tv_physical_state')->default('standby'); // standby, screen_on, unreachable
            $table->string('current_state')->default('available'); // available, active_prepaid, active_postpaid, paused, payment_pending, maintenance
            $table->unsignedInteger('consecutive_on_pings')->default(0);
            $table->timestamp('first_detected_on_at')->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
