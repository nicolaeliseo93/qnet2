<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the province geo reference to an address, completing the
 * country → state → province → city chain. Nullable + nullOnDelete, exactly
 * like the existing city_id / state_id / country_id columns: losing the
 * reference row must never delete the address it belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('state_id')->constrained('provinces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('province_id');
        });
    }
};
