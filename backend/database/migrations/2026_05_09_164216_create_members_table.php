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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained();
            $table->foreignId('customer_id')->unique()->constrained();
            $table->string('id_member')->nullable()->unique();
            $table->enum('status_member', ['Pending', 'Aktif', 'Tidak Aktif', 'Ditolak'])->default('Pending');
            $table->date('tanggal_survey')->nullable();
            $table->date('tanggal_aktif')->nullable();
            $table->date('tanggal_exp')->nullable();
            $table->foreignId('surveyor_id')->nullable()->constrained('users');
            $table->text('catatan')->nullable();

            // Identity & Documents (Paths)
            $table->string('foto_wajah')->nullable();
            $table->string('dokumen_identitas')->nullable(); // KTP/SIM
            $table->string('identitas_type')->nullable(); // KTP, SIM, Paspor
            $table->json('dokumen_pendukung')->nullable(); // Array of paths (KK, etc)

            // Job Information
            $table->string('nama_kantor')->nullable();
            $table->string('alamat_kantor')->nullable();
            $table->string('kontak_kantor')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('nama_atasan')->nullable();
            $table->string('pekerjaan_status')->nullable(); // Pelajar/PNS/Swasta/Wiraswasta

            // Family & Social
            $table->string('pj_nama')->nullable();
            $table->string('pj_kontak')->nullable();
            $table->string('pj_hubungan')->nullable();
            $table->string('ortu_nama')->nullable();
            $table->string('ortu_alamat')->nullable();
            $table->string('ortu_kontak')->nullable();
            $table->string('status_pernikahan')->nullable();
            $table->string('rumah_status')->nullable(); // Ruko/Permanen/Semi Permanen
            $table->string('rumah_lokasi')->nullable(); // Umum/Biasa/Gang

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
