<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the optional human `label` from addresses: the field is no longer
 * offered on any form nor surfaced by the API. A separate, reversible migration
 * (never editing the committed create migration) so the schema stays versioned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('label')->nullable()->after('addressable_id');
        });
    }
};
