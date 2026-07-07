<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `product_type` classification to products (spec 0017). Backed by
 * App\Enums\ProductType; NOT NULL with a SERVICE default so existing rows
 * (and creates that omit it) are backfilled to the only value the catalogue
 * currently exposes. Indexed because it is a sortable/set-filterable grid
 * column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_type', 32)->default('SERVICE')->after('category_id');
            $table->index('product_type');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['product_type']);
            $table->dropColumn('product_type');
        });
    }
};
