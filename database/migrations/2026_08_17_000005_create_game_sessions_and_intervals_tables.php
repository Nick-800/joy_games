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
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('session_type'); // prepaid, postpaid
            $table->string('status')->default('active'); // active, paused, payment_pending, completed, cancelled
            $table->unsignedInteger('allocated_minutes')->nullable();
            $table->boolean('allow_overtime')->default(true);
            $table->timestamp('started_at');
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('total_paused_seconds')->default(0);
            $table->string('pause_reason')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('time_amount_millimes')->default(0);
            $table->unsignedInteger('retail_amount_millimes')->default(0);
            $table->unsignedInteger('discount_amount_millimes')->default(0);
            $table->unsignedInteger('final_total_millimes')->default(0);
            $table->string('payment_status')->default('unpaid'); // unpaid, paid
            $table->string('payment_method')->nullable(); // cash, card, split
            $table->unsignedInteger('cash_received_millimes')->nullable();
            $table->unsignedInteger('cash_change_millimes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('game_session_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained('game_sessions')->cascadeOnDelete();
            $table->foreignId('pricing_tier_id')->constrained('pricing_tiers')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('billable_minutes')->default(0);
            $table->unsignedInteger('rate_per_hour_millimes');
            $table->decimal('station_multiplier', 4, 2)->default(1.00);
            $table->unsignedInteger('subtotal_millimes')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_session_intervals');
        Schema::dropIfExists('game_sessions');
    }
};
