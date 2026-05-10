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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kode_booking')->unique();
            $table->enum('status', ['follow_up', 'confirm', 'waiting_list', 'rental_unit', 'selesai', 'batal'])->default('follow_up');
            $table->unsignedBigInteger('harga_dealing')->nullable();
            $table->unsignedBigInteger('dp')->nullable();
            $table->unsignedBigInteger('rekening_dp_id')->nullable(); // Constraint will be added in Phase 3
            $table->string('tujuan')->nullable();
            $table->text('alamat_penjemputan')->nullable();
            $table->text('catatan')->nullable();
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
        Schema::dropIfExists('bookings');
    }
};
