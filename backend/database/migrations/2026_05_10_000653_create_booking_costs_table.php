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
        Schema::create('booking_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_detail_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['biaya', 'diskon']);
            $table->string('label');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_costs');
    }
};
