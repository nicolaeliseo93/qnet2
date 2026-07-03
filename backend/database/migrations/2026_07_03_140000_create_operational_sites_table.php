<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational site entity (spec 0011 — "Sedi operative"): a physical
 * location identified entirely by its address (comune/via/CAP/provincia/
 * regione). No own name/label column — the site IS its address, which lives
 * on the polymorphic `addresses` table (HasAddresses), never as flat columns
 * here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_sites', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            // Default sort on created_at (spec 0011).
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_sites');
    }
};
