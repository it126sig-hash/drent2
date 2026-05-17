<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_operational_fund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_operational_fund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 150);
            $table->unsignedBigInteger('planned_amount');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_operational_fund_items');
    }
};
