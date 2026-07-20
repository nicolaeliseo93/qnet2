<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App-wide singleton holding the ordered mass-import plan (spec 0046): which
 * migration sources (spec 0013) the "Import all" button runs and in what order.
 * `sources` is an ordered JSON array of `{source, enabled}`; one row, last save
 * wins. When absent the default is App\Migrations\MigrationOrder::PHASES.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_plans', function (Blueprint $table) {
            $table->id();
            $table->json('sources');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_plans');
    }
};
