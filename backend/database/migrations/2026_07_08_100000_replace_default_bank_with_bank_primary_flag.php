<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the "preferred bank" concept off the site (`company_sites.default_bank_id`
 * FK) and onto the bank rows themselves: a `company_site_banks.is_primary` flag,
 * mirroring the single-primary invariant already used by contacts/addresses
 * (at most one primary per owner, enforced in BankService). Removing the FK also
 * dissolves the two-table reference cycle (see the create migrations' docblocks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_site_banks', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('notes');
        });

        Schema::table('company_sites', function (Blueprint $table) {
            $table->dropForeign(['default_bank_id']);
            $table->dropColumn('default_bank_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_sites', function (Blueprint $table) {
            $table->unsignedBigInteger('default_bank_id')->nullable();
        });

        Schema::table('company_sites', function (Blueprint $table) {
            $table->foreign('default_bank_id')->references('id')->on('company_site_banks')->nullOnDelete();
        });

        Schema::table('company_site_banks', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
