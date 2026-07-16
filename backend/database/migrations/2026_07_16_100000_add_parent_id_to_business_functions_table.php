<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business function hierarchy (spec 0010 REV): unlimited-depth parent/child
 * tree, mirroring product_categories' `parent_id` (restrictOnDelete — a
 * function with children must be reparented/deleted first, enforced again at
 * the service level by BusinessFunctionService::delete()). Purely additive:
 * no existing column is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_functions', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('manager_id')
                ->constrained('business_functions')->restrictOnDelete();

            $table->index(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('business_functions', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'name']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
