<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable()->after('catatan');
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable()->after('confirmed_by');
            $table->foreignId('handled_by')->nullable()->after('handled_at')->constrained('users')->nullOnDelete();
            $table->dateTime('checked_out_at')->nullable()->after('handled_by');
            $table->foreignId('checked_out_by')->nullable()->after('checked_out_at')->constrained('users')->nullOnDelete();
            $table->dateTime('returned_at')->nullable()->after('checked_out_by');
            $table->dateTime('completed_at')->nullable()->after('returned_at');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completed_at');
            $table->dropColumn('returned_at');
            $table->dropConstrainedForeignId('checked_out_by');
            $table->dropColumn('checked_out_at');
            $table->dropConstrainedForeignId('handled_by');
            $table->dropColumn('handled_at');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmed_at');
        });
    }
};
