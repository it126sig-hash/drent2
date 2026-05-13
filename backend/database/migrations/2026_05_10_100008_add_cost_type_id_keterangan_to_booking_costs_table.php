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
        Schema::table('booking_costs', function (Blueprint $table) {
            $table->foreignId('cost_type_id')->nullable()->after('booking_detail_id')->constrained()->nullOnDelete();
            $table->text('keterangan')->nullable()->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_type_id');
            $table->dropColumn('keterangan');
        });
    }
};
