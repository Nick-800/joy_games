<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unsignedInteger('previous_final_total_millimes')->nullable()->after('final_total_millimes');
            $table->timestamp('recomputed_at')->nullable()->after('previous_final_total_millimes');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['previous_final_total_millimes', 'recomputed_at']);
        });
    }
};
