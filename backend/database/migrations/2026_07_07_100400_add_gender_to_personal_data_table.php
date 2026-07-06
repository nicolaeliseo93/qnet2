<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the biological sex (GenderEnum: male | female) to a personal-data card.
 *
 * Meaningful only for a natural person (type=individual), so it is left
 * nullable — a company card keeps it null. Not a fiscal identifier nor a dedup
 * key, so it is un-indexed. Purely additive: no existing column is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('birth_date');
        });
    }

    public function down(): void
    {
        Schema::table('personal_data', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
