<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the SDI recipient code (Codice Destinatario) to a personal-data card.
 *
 * It is the 6/7-char routing address used by the Italian e-invoicing system
 * (Sistema di Interscambio) and is meaningful only for legal entities
 * (type=company). Unlike tax_code/vat_number it is not a sensitive fiscal
 * identifier nor a dedup key, so it is left un-indexed and not hidden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->string('sdi_code')->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->dropColumn('sdi_code');
        });
    }
};
