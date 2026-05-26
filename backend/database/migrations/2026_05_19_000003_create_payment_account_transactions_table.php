<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->foreignId('finance_category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['transfer_out', 'transfer_in', 'other_income', 'other_expense', 'balance_adjustment']);
            $table->string('transfer_group_id', 64)->nullable();
            $table->unsignedBigInteger('amount');
            $table->bigInteger('signed_amount');
            $table->bigInteger('balance_before');
            $table->bigInteger('balance_after');
            $table->timestamp('transaction_at');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'transaction_at'], 'payment_account_transactions_scope_date_idx');
            $table->index(['payment_account_id', 'transaction_at'], 'payment_account_transactions_account_date_idx');
            $table->index('transfer_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_account_transactions');
    }
};
