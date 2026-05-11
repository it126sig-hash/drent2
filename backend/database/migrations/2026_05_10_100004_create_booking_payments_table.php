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
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->enum('payment_type', ['dp', 'cicilan', 'pelunasan']);
            $table->text('catatan')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('reallocated_from_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('reallocated_from_id')
                  ->references('id')
                  ->on('booking_payments')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
