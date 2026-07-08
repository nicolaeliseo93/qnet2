<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the site type classification to an address (spec 0020): which kind of
 * location it represents (legal seat, delivery, billing, operational site).
 * Shared column on the polymorphic `addresses` table — DB default `billing`
 * keeps every existing owner (Users/Referents/Companies) behavior-preserving;
 * only the Registries form renders the select (showSiteType opt-in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('site_type')->default('billing')->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('site_type');
        });
    }
};
