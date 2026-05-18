<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_to_rent_bills', function (Blueprint $table) {
            $table->string('public_token')->nullable()->unique()->after('bill_number');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->foreignId('void_requested_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('void_requested_at')->nullable()->after('void_requested_by');
            $table->foreignId('void_approved_by')->nullable()->after('void_requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('void_approved_at')->nullable()->after('void_approved_by');
            $table->foreignId('void_rejected_by')->nullable()->after('void_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('void_rejected_at')->nullable()->after('void_rejected_by');
            $table->text('void_rejection_note')->nullable()->after('void_rejected_at');
        });

        Schema::table('rent_to_rent_payments', function (Blueprint $table) {
            $table->string('status')->default('active')->after('amount');
            $table->timestamp('voided_at')->nullable()->after('paid_at');
        });

        DB::table('rent_to_rent_bills')
            ->whereNull('public_token')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($bill) {
                do {
                    $token = Str::random(48);
                } while (DB::table('rent_to_rent_bills')->where('public_token', $token)->exists());

                DB::table('rent_to_rent_bills')
                    ->where('id', $bill->id)
                    ->update(['public_token' => $token]);
            });
    }

    public function down(): void
    {
        Schema::table('rent_to_rent_payments', function (Blueprint $table) {
            $table->dropColumn(['status', 'voided_at']);
        });

        Schema::table('rent_to_rent_bills', function (Blueprint $table) {
            $table->dropForeign(['void_requested_by']);
            $table->dropForeign(['void_approved_by']);
            $table->dropForeign(['void_rejected_by']);
            $table->dropColumn([
                'public_token',
                'void_reason',
                'void_requested_by',
                'void_requested_at',
                'void_approved_by',
                'void_approved_at',
                'void_rejected_by',
                'void_rejected_at',
                'void_rejection_note',
            ]);
        });
    }
};
