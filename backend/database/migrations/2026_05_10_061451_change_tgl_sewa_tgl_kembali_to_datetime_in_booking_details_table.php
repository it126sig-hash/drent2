<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->dateTime('tgl_sewa')->change();
            $table->dateTime('tgl_kembali')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->date('tgl_sewa')->change();
            $table->date('tgl_kembali')->change();
        });
    }
};
