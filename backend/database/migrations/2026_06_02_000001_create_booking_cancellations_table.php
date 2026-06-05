<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('ada_refund')->default(false);
            $table->unsignedBigInteger('nominal_refund')->nullable();
            $table->string('bank_refund')->nullable();
            $table->string('no_rek_refund')->nullable();
            $table->string('nama_rek_refund')->nullable();
            $table->text('catatan_refund')->nullable();
            $table->boolean('sudah_bayar_refund')->default(false);
            $table->foreignId('payment_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('dibayar_at')->nullable();
            $table->foreignId('dibayar_oleh')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'ada_refund', 'sudah_bayar_refund'], 'bc_tenant_branch_refund_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_cancellations');
    }
};
