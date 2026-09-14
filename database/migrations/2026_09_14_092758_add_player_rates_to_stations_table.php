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
        Schema::table('stations', function (Blueprint $table) {
            $table->unsignedInteger('hourly_rate_1_2_millimes')->nullable()->after('default_hourly_rate_millimes');
            $table->unsignedInteger('hourly_rate_3_4_millimes')->nullable()->after('hourly_rate_1_2_millimes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate_1_2_millimes', 'hourly_rate_3_4_millimes']);
        });
    }
};
