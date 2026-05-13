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
        Schema::create('pricing_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_type_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['biaya', 'diskon'])->default('biaya');
            $table->string('label');
            $table->unsignedBigInteger('amount')->default(0);
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_package_items');
    }
};
