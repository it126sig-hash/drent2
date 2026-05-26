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
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedBigInteger('harga_all_in_1_minggu')->default(0)->after('harga_all_in');
            $table->unsignedBigInteger('harga_all_in_1_bulan')->default(0)->after('harga_all_in_1_minggu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['harga_all_in_1_minggu', 'harga_all_in_1_bulan']);
        });
    }
};
