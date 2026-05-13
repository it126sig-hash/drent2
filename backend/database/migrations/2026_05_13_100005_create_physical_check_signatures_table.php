<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_check_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_check_id')->constrained()->cascadeOnDelete();
            $table->enum('signer_type', ['inspector', 'customer_driver']);
            $table->string('signer_name')->nullable();
            $table->string('signature_path');
            $table->dateTime('signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_check_signatures');
    }
};
