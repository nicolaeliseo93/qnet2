<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot assigning an attribute to a category (spec 0017): `is_required` and
 * `sort_order` are per-assignment (the SAME attribute can be optional on one
 * category and required on another). A category's EFFECTIVE attributes are
 * its own assignments UNION every ancestor's (ProductCategoryService,
 * resolved in PHP by walking `parent_id`, never a raw recursive query).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_category');
    }
};
