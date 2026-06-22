<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_card_funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fleet_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount')->default(0);
            $table->date('paid_at')->nullable();
            $table->string('recipient_destination', 150);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending_driver_acceptance', 'accepted', 'void_requested', 'cancelled', 'closed'])
                ->default('pending_driver_acceptance');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('close_note')->nullable();
            $table->string('status_before_void')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('void_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('void_requested_at')->nullable();
            $table->foreignId('void_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('void_approved_at')->nullable();
            $table->foreignId('void_rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('void_rejected_at')->nullable();
            $table->text('void_rejection_note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['fleet_card_id', 'status']);
            $table->index(['driver_id', 'status']);
            $table->index(['booking_detail_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_card_funds');
    }
};
