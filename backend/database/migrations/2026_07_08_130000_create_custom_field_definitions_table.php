<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Universal custom fields (spec 0021) — the metadata registry. One row per
 * field definition, scoped to a custom-fieldable entity_type (a domain
 * derived from the existing TableRegistry/AuthorizationRegistry, e.g.
 * "companies"). Values themselves live in `custom_field_values` (JSON-per-
 * entity), NOT here — this table is metadata only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type');
            $table->string('key');
            $table->string('type');
            $table->string('label');
            $table->text('description')->nullable();
            $table->text('help_text')->nullable();
            $table->string('placeholder')->nullable();
            $table->string('icon')->nullable();
            $table->string('group')->nullable();
            $table->string('tab')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('default_value')->nullable();
            $table->json('config')->nullable();
            $table->json('validation')->nullable();
            $table->json('relation_target')->nullable();
            $table->boolean('is_indexed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['entity_type', 'key']);
            $table->index(['entity_type', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
