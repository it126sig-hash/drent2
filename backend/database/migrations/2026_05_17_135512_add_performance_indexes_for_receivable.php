<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan index untuk mempercepat query di ReceivableService.
     */
    public function up(): void
    {
        // Index untuk query Booking::query() di ReceivableService::list()
        // Filter: tenant_id, branch_id, status (whereNotIn 'batal'), soft-delete
        Schema::table('bookings', function (Blueprint $table) {
            if (! $this->indexExists('bookings', 'bookings_tenant_branch_status_idx')) {
                $table->index(['tenant_id', 'branch_id', 'status', 'deleted_at'], 'bookings_tenant_branch_status_idx');
            }
        });

        // Index untuk query Invoice di ReceivableService::invoices()
        // Filter: tenant_id, branch_id, status
        Schema::table('invoices', function (Blueprint $table) {
            if (! $this->indexExists('invoices', 'invoices_tenant_branch_status_idx')) {
                $table->index(['tenant_id', 'branch_id', 'status', 'deleted_at'], 'invoices_tenant_branch_status_idx');
            }
        });

        // Index untuk query BookingPayment di ReceivableService::paymentHistory()
        // Filter: invoice_payment_id IS NULL, booking_id, paid_at sort
        Schema::table('booking_payments', function (Blueprint $table) {
            if (! $this->indexExists('booking_payments', 'booking_payments_invoice_booking_idx')) {
                $table->index(['booking_id', 'invoice_payment_id', 'paid_at'], 'booking_payments_invoice_booking_idx');
            }
        });

        // Index untuk query Payment (invoice payments) di paymentHistory()
        // Sort: paid_at DESC, filter via invoice (invoice_id)
        Schema::table('payments', function (Blueprint $table) {
            if (! $this->indexExists('payments', 'payments_invoice_paid_at_idx')) {
                $table->index(['invoice_id', 'paid_at'], 'payments_invoice_paid_at_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_tenant_branch_status_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_tenant_branch_status_idx');
        });

        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropIndex('booking_payments_invoice_booking_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_invoice_paid_at_idx');
        });
    }

    /**
     * Check if index already exists to prevent duplicate index error.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return false;
        }

        $indexes = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
