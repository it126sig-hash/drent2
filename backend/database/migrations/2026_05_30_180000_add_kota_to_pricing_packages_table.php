<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->string('kota_asal', 100)->nullable()->after('nama_paket');
            $table->string('kota_tujuan', 100)->nullable()->after('kota_asal');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropColumn(['kota_asal', 'kota_tujuan']);
        });
    }
};
