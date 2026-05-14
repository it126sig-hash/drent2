<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('rental_unit_return_status')->nullable()->after('completed_by');
            $table->text('rental_unit_return_reason')->nullable()->after('rental_unit_return_status');
            $table->foreignId('rental_unit_return_requested_by')->nullable()->after('rental_unit_return_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('rental_unit_return_requested_at')->nullable()->after('rental_unit_return_requested_by');
            $table->foreignId('rental_unit_return_approved_by')->nullable()->after('rental_unit_return_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rental_unit_return_approved_at')->nullable()->after('rental_unit_return_approved_by');
            $table->foreignId('rental_unit_return_rejected_by')->nullable()->after('rental_unit_return_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rental_unit_return_rejected_at')->nullable()->after('rental_unit_return_rejected_by');
            $table->text('rental_unit_return_rejection_note')->nullable()->after('rental_unit_return_rejected_at');

            $table->index(['branch_id', 'rental_unit_return_status']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'rental_unit_return_status']);
            $table->dropConstrainedForeignId('rental_unit_return_requested_by');
            $table->dropConstrainedForeignId('rental_unit_return_approved_by');
            $table->dropConstrainedForeignId('rental_unit_return_rejected_by');
            $table->dropColumn([
                'rental_unit_return_status',
                'rental_unit_return_reason',
                'rental_unit_return_requested_at',
                'rental_unit_return_approved_at',
                'rental_unit_return_rejected_at',
                'rental_unit_return_rejection_note',
            ]);
        });
    }
};
