<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Typed EAV storage for a product's dynamic attribute values (spec 0017):
 * one row per (product, attribute), the value routed into the column
 * matching the attribute's data_type (value_string/value_integer/
 * value_decimal/value_boolean), or `option_id` for ENUM. `option_id` is
 * nullOnDelete: removing an option only clears the reference, it never
 * cascades a product-value delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->text('value_string')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 20, 6)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->foreignId('option_id')->nullable()->constrained('attribute_options')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
