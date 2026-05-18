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
        Schema::table('rent_to_rent_debts', function (Blueprint $table) {
            $table->unsignedBigInteger('cached_total_amount')->default(0)->after('status');
            $table->unsignedBigInteger('cached_paid_amount')->default(0)->after('cached_total_amount');
            $table->string('cached_payment_status')->default('open')->after('cached_paid_amount');

            $table->index('cached_payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rent_to_rent_debts', function (Blueprint $table) {
            $table->dropIndex(['cached_payment_status']);
            $table->dropColumn(['cached_total_amount', 'cached_paid_amount', 'cached_payment_status']);
        });
    }
};
