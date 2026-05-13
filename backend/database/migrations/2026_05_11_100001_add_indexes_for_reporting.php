<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->index('paid_at');
            $table->index(['payment_account_id', 'paid_at']);
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->index('refunded_at');
            $table->index(['payment_account_id', 'refunded_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index('status');
            $table->index('branch_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropIndex(['paid_at']);
            $table->dropIndex(['payment_account_id', 'paid_at']);
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropIndex(['refunded_at']);
            $table->dropIndex(['payment_account_id', 'refunded_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['branch_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
