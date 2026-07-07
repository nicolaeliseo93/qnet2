<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-category opt-out of attribute inheritance (spec 0017 rev). When
 * `inherits_attributes` is false a category becomes an inheritance ROOT: it
 * pulls no attributes from its ancestry, and — because CategoryHierarchy walks
 * the chain node by node — that barrier also cuts its descendants off from
 * everything above it. NOT NULL, defaulting to true so existing categories
 * (and creates that omit it) keep the original always-inherit behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('inherits_attributes')->default(true)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('inherits_attributes');
        });
    }
};
