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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('operational_revert_status')->nullable()->after('is_operational_completed');
            $table->text('operational_revert_reason')->nullable()->after('operational_revert_status');
            $table->foreignId('operational_revert_requested_by')->nullable()->after('operational_revert_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('operational_revert_requested_at')->nullable()->after('operational_revert_requested_by');
            $table->foreignId('operational_revert_approved_by')->nullable()->after('operational_revert_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('operational_revert_approved_at')->nullable()->after('operational_revert_approved_by');
            $table->foreignId('operational_revert_rejected_by')->nullable()->after('operational_revert_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('operational_revert_rejected_at')->nullable()->after('operational_revert_rejected_by');
            $table->text('operational_revert_rejection_note')->nullable()->after('operational_revert_rejected_at');

            $table->index(['branch_id', 'operational_revert_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'operational_revert_status']);
            $table->dropConstrainedForeignId('operational_revert_requested_by');
            $table->dropConstrainedForeignId('operational_revert_approved_by');
            $table->dropConstrainedForeignId('operational_revert_rejected_by');
            $table->dropColumn([
                'operational_revert_status',
                'operational_revert_reason',
                'operational_revert_requested_at',
                'operational_revert_approved_at',
                'operational_revert_rejected_at',
                'operational_revert_rejection_note',
            ]);
        });
    }
};
