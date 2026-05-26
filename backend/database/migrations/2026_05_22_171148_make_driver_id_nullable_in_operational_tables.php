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
        Schema::table('driver_operational_funds', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable()->change();
        });

        Schema::table('driver_operational_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_operational_fund_id')->nullable()->change();
            $table->unsignedBigInteger('driver_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_operational_funds', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable(false)->change();
        });

        Schema::table('driver_operational_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_operational_fund_id')->nullable(false)->change();
            $table->unsignedBigInteger('driver_id')->nullable(false)->change();
        });
    }
};
