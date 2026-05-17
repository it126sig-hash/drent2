<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_to_rent_debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_owner_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_detail_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_override')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rent_to_rent_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rental_owner_id')->constrained()->cascadeOnDelete();
            $table->string('bill_number')->unique();
            $table->string('status')->default('generated');
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rent_to_rent_bill_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rent_to_rent_bill_id');
            $table->unsignedBigInteger('rent_to_rent_debt_id');
            $table->foreignId('booking_detail_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->default(0);
            $table->timestamps();

            $table->foreign('rent_to_rent_bill_id', 'rtr_items_bill_fk')->references('id')->on('rent_to_rent_bills')->cascadeOnDelete();
            $table->foreign('rent_to_rent_debt_id', 'rtr_items_debt_fk')->references('id')->on('rent_to_rent_debts')->cascadeOnDelete();
            $table->unique(['rent_to_rent_bill_id', 'rent_to_rent_debt_id'], 'rent_to_rent_bill_debt_unique');
        });

        Schema::create('rent_to_rent_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rent_to_rent_bill_id');
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('rent_to_rent_bill_id', 'rtr_payments_bill_fk')->references('id')->on('rent_to_rent_bills')->cascadeOnDelete();
        });

        Schema::create('rent_to_rent_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rent_to_rent_payment_id');
            $table->unsignedBigInteger('rent_to_rent_bill_item_id');
            $table->unsignedBigInteger('rent_to_rent_debt_id');
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->foreign('rent_to_rent_payment_id', 'rtr_alloc_payment_fk')->references('id')->on('rent_to_rent_payments')->cascadeOnDelete();
            $table->foreign('rent_to_rent_bill_item_id', 'rtr_alloc_item_fk')->references('id')->on('rent_to_rent_bill_items')->cascadeOnDelete();
            $table->foreign('rent_to_rent_debt_id', 'rtr_alloc_debt_fk')->references('id')->on('rent_to_rent_debts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_to_rent_payment_allocations');
        Schema::dropIfExists('rent_to_rent_payments');
        Schema::dropIfExists('rent_to_rent_bill_items');
        Schema::dropIfExists('rent_to_rent_bills');
        Schema::dropIfExists('rent_to_rent_debts');
    }
};
