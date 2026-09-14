<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->string('timezone', 64)->default('Africa/Tripoli')->after('currency_symbol');
            $table->unsignedInteger('prepaid_overtime_grace_minutes')->default(5)->after('allow_overtime_default');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'prepaid_overtime_grace_minutes']);
        });
    }
};
