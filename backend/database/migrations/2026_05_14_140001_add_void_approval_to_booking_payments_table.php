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
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->string('status')->default('active')->after('payment_type');
            $table->text('void_reason')->nullable()->after('catatan');
            $table->foreignId('void_requested_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('void_requested_at')->nullable()->after('void_requested_by');
            $table->foreignId('void_approved_by')->nullable()->after('void_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('void_approved_at')->nullable()->after('void_approved_by');
            $table->foreignId('void_rejected_by')->nullable()->after('void_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('void_rejected_at')->nullable()->after('void_rejected_by');
            $table->text('void_rejection_note')->nullable()->after('void_rejected_at');

            $table->index(['booking_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropIndex(['booking_id', 'status']);
            $table->dropConstrainedForeignId('void_requested_by');
            $table->dropConstrainedForeignId('void_approved_by');
            $table->dropConstrainedForeignId('void_rejected_by');
            $table->dropColumn([
                'status',
                'void_reason',
                'void_requested_at',
                'void_approved_at',
                'void_rejected_at',
                'void_rejection_note',
            ]);
        });
    }
};
