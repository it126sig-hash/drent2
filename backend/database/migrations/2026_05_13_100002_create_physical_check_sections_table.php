<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_check_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_check_id')->constrained()->cascadeOnDelete();
            $table->string('section', 40);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['physical_check_id', 'section']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_check_sections');
    }
};
