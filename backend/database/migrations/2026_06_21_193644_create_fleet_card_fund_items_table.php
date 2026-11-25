<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_card_fund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_card_fund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label', 150);
            $table->unsignedBigInteger('planned_amount');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_card_fund_items');
    }
};
