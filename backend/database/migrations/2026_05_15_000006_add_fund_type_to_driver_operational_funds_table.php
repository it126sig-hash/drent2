<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_operational_funds', function (Blueprint $table) {
            $table->enum('fund_type', ['operational', 'salary'])
                ->default('operational')
                ->after('driver_id');
            $table->index(['fund_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('driver_operational_funds', function (Blueprint $table) {
            $table->dropIndex(['fund_type', 'status']);
            $table->dropColumn('fund_type');
        });
    }
};
