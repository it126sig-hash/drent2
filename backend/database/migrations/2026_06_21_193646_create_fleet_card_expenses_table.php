<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_card_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fleet_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fleet_card_fund_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_account_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['expense', 'return'])->default('expense');
            $table->unsignedBigInteger('amount');
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->enum('status', ['submitted', 'approved', 'rejected', 'void_requested'])->default('submitted');
            $table->enum('source', ['driver', 'finance'])->default('driver');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
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
            $table->index(['booking_detail_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_card_expenses');
    }
};
