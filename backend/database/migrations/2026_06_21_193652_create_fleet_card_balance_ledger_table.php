<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_card_balance_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fleet_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fleet_card_fund_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fleet_card_expense_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['credit', 'debit']);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('description', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fleet_card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_card_balance_ledgers');
    }
};
