<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discrete option list of an ENUM-typed custom field definition (spec 0021).
 * Managed as a nested full-replace on the owning definition — never audited
 * independently (mirrors AttributeOption for the product EAV).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $table->string('value');
            $table->string('label');
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['definition_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_options');
    }
};
