<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_operational_funds', function (Blueprint $table) {
            $table->foreignId('payment_account_id')
                ->nullable()
                ->after('driver_id')
                ->constrained()
                ->nullOnDelete();
            $table->timestamp('closed_at')->nullable()->after('cancelled_by');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            $table->text('close_note')->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('driver_operational_funds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_account_id');
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['closed_at', 'close_note']);
        });
    }
};
