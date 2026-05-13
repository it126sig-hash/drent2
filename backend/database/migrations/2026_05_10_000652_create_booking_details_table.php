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
        Schema::create('booking_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_placeholder')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->date('tgl_sewa');
            $table->date('tgl_kembali');
            $table->unsignedBigInteger('harga_mobil')->nullable();
            $table->unsignedBigInteger('diskon_mobil')->nullable()->default(0);
            $table->enum('detail_type', ['initial', 'extend', 'rolling'])->default('initial');
            $table->enum('status', ['draft', 'aktif', 'selesai'])->default('draft');
            $table->timestamps();
            $table->softDeletes();

            // TODO: konfirmasi strategi arsip (soft-delete vs tabel terpisah vs is_archived flag)
            // Keputusan ini belum final per 2026-05-10.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_details');
    }
};
