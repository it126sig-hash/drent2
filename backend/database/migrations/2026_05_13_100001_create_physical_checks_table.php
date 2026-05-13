<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_detail_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['departure', 'return']);
            $table->enum('status', ['requested', 'completed', 'skipped'])->default('requested');
            $table->unsignedInteger('km_odometer')->nullable();
            $table->string('fuel_level', 50)->nullable();
            $table->decimal('fuel_marker_x', 5, 2)->nullable();
            $table->decimal('fuel_marker_y', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('requested_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('inspected_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('skipped_at')->nullable();
            $table->foreignId('skipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id', 'type', 'status']);
            $table->index(['booking_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_checks');
    }
};
