<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->string('cancel_extend_status')->nullable()->after('status');
            $table->text('cancel_extend_reason')->nullable()->after('cancel_extend_status');
            $table->foreignId('cancel_extend_requested_by')->nullable()->after('cancel_extend_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancel_extend_requested_at')->nullable()->after('cancel_extend_requested_by');
            $table->foreignId('cancel_extend_approved_by')->nullable()->after('cancel_extend_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancel_extend_approved_at')->nullable()->after('cancel_extend_approved_by');
            $table->foreignId('cancel_extend_rejected_by')->nullable()->after('cancel_extend_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancel_extend_rejected_at')->nullable()->after('cancel_extend_rejected_by');
            $table->text('cancel_extend_rejection_note')->nullable()->after('cancel_extend_rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancel_extend_requested_by');
            $table->dropConstrainedForeignId('cancel_extend_approved_by');
            $table->dropConstrainedForeignId('cancel_extend_rejected_by');
            $table->dropColumn([
                'cancel_extend_status',
                'cancel_extend_reason',
                'cancel_extend_requested_at',
                'cancel_extend_approved_at',
                'cancel_extend_rejected_at',
                'cancel_extend_rejection_note',
            ]);
        });
    }
};
