<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_migration_maps', function (Blueprint $table) {
            $table->id();
            $table->string('source_database', 100);
            $table->string('legacy_table', 100);
            $table->string('legacy_id', 100);
            $table->string('target_table', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('canonical_legacy_id', 100)->nullable();
            $table->string('decision', 80);
            $table->unsignedSmallInteger('confidence_score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_database', 'legacy_table', 'legacy_id'], 'legacy_migration_maps_source_unique');
            $table->index(['target_table', 'target_id']);
            $table->index(['decision', 'confidence_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_migration_maps');
    }
};
