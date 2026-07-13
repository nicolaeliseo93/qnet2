<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `business_function_id` to `product_categories` (spec 0023): the
 * category's OWN business function assignment, nullable, FK -> business_functions
 * with `nullOnDelete` — same pattern as `employment_profiles.business_function_id`.
 * A category's EFFECTIVE function (own or inherited, resolved by
 * CategoryHierarchy) is a read-side concern; this column only ever stores the
 * category's OWN value, never the inherited one. Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('business_function_id')->nullable()->after('parent_id')
                ->constrained()->nullOnDelete();
            $table->index('business_function_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropForeign(['business_function_id']);
            $table->dropIndex(['business_function_id']);
            $table->dropColumn('business_function_id');
        });
    }
};
