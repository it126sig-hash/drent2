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
        // 1. Change type in payment_account_transactions to string so it can support new types
        Schema::table('payment_account_transactions', function (Blueprint $table) {
            $table->string('type')->change();
        });

        // 2. Add payment_account_id to driver_operational_expenses for direct booking expenses
        Schema::table('driver_operational_expenses', function (Blueprint $table) {
            $table->foreignId('payment_account_id')
                ->nullable()
                ->after('cost_type_id')
                ->constrained('payment_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_operational_expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_account_id');
        });

        Schema::table('payment_account_transactions', function (Blueprint $table) {
            $table->enum('type', ['transfer_out', 'transfer_in', 'other_income', 'other_expense', 'balance_adjustment'])->change();
        });
    }
};
