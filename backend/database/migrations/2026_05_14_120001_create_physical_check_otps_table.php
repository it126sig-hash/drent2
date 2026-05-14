<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_check_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_check_id')->constrained()->cascadeOnDelete();
            $table->string('email', 150);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->text('requested_user_agent')->nullable();
            $table->timestamps();

            $table->index(['physical_check_id', 'email', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_check_otps');
    }
};
