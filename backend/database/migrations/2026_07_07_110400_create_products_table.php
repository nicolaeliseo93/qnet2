<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product entity (spec 0017): generic fields only (name/description/cost/
 * price/category). Dynamic, category-driven attribute values live in
 * `product_attribute_values` (next migration). `category_id` restricts on
 * delete — a category with products cannot be removed (ProductCategoryService
 * also guards this at the service level before the FK is even reached).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('category_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
