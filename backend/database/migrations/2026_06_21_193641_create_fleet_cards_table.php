<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('label', 150)->nullable();
            $table->unsignedBigInteger('saldo')->default(0);
            $table->enum('status', ['active', 'inactive', 'lost'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_cards');
    }
};
