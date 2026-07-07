<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product category tree (spec 0017): unlimited-depth parent/child hierarchy.
 * `parent_id` restricts on delete (restrictOnDelete) — a category with
 * children must be reparented/deleted first, mirroring the service-level
 * restrictive-delete guard (ProductCategoryService::delete) that also checks
 * for associated products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
