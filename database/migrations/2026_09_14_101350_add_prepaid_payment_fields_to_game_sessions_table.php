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
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->string('prepaid_payment_timing')->nullable()->after('session_type'); // 'before', 'after'
            $table->unsignedInteger('upfront_paid_millimes')->default(0)->after('final_total_millimes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['prepaid_payment_timing', 'upfront_paid_millimes']);
        });
    }
};
