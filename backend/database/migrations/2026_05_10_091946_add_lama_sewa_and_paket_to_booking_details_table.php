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
        Schema::table('booking_details', function (Blueprint $table) {
            $table->unsignedInteger('lama_sewa')->nullable()->after('diskon_mobil');
            $table->enum('paket_sewa', ['harian', 'mingguan', 'bulanan'])->nullable()->after('lama_sewa');
            $table->enum('pricing_mode', ['all_in', 'non_all_in'])->default('non_all_in')->after('paket_sewa');
            $table->unsignedBigInteger('pricing_package_id')->nullable()->after('pricing_mode');
            $table->unsignedBigInteger('harga_all_in')->nullable()->after('pricing_package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->dropColumn(['lama_sewa', 'paket_sewa', 'pricing_mode', 'pricing_package_id', 'harga_all_in']);
        });
    }
};
