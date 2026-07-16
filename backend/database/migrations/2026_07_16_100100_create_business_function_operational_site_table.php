<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the business-function <-> operational-site relation (spec 0010
 * REV): a function may be associated to 0..n operational sites. Both sides
 * cascade: deleting either the function or the site drops the membership
 * row, no orphaned pivot data — mirroring sector_registry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_function_operational_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_function_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_site_id')->constrained()->cascadeOnDelete();

            $table->unique(
                ['business_function_id', 'operational_site_id'],
                'bf_op_site_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_function_operational_site');
    }
};
