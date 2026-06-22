<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('child_invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->default(0); // sisa tagihan child saat digabung
            $table->timestamps();

            $table->unique(['parent_invoice_id', 'child_invoice_id']);
            $table->unique('child_invoice_id'); // 1 child hanya boleh punya 1 parent
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_invoices');
    }
};
