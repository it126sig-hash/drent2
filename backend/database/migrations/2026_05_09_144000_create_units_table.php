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
        Schema::create('units', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $blueprint->foreignId('rental_owner_id')->nullable()->constrained()->nullOnDelete();
            
            $blueprint->string('tipe', 100);
            $blueprint->string('merk', 100)->nullable();
            $blueprint->year('tahun');
            $blueprint->string('no_polisi', 20);
            
            $blueprint->unsignedBigInteger('harga_1_hari');
            $blueprint->unsignedBigInteger('harga_1_minggu');
            $blueprint->unsignedBigInteger('harga_1_bulan');
            
            $blueprint->unsignedBigInteger('modal_1_hari');
            $blueprint->unsignedBigInteger('modal_1_minggu');
            $blueprint->unsignedBigInteger('modal_1_bulan');
            
            $blueprint->enum('status', ['Aktif', 'Tidak Aktif', 'Dalam Servis'])->default('Aktif');
            $blueprint->text('catatan')->nullable();
            
            $blueprint->timestamps();
            $blueprint->softDeletes();
            
            // Unik per tenant
            $blueprint->unique(['tenant_id', 'no_polisi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
