<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_check_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_check_id')->constrained()->cascadeOnDelete();
            $table->foreignId('physical_check_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_label', 100);
            $table->boolean('is_present')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_check_checklists');
    }
};
