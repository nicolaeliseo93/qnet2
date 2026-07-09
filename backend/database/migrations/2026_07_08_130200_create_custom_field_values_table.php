<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Universal custom fields (spec 0021) — JSON-per-entity value storage. One
 * row per (entity_type, entity_id): `values` is a map {key: typed value},
 * relations N-N stored as an array of ids. Read = one join per page;
 * filtering k custom fields = one join, not k (vs row-per-value EAV).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('values');
            $table->timestamps();

            $table->unique(['entity_type', 'entity_id']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
