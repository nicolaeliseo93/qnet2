<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank accounts owned by a company site (spec 0020), a real 1→N (FK
 * `company_site_id`, not a morph) unlike the polymorphic address/logo — see
 * BankService for the diff-by-id sync invariant. Cascades on the site's
 * delete.
 *
 * Also completes the create_company_sites_table migration by adding the
 * `default_bank_id` foreign key now that this table exists (the two tables
 * reference each other — see that migration's docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_site_banks', function (Blueprint $table) {
            $table->id();
            // `after('id')` is an ALTER-TABLE-only modifier; in a fresh CREATE
            // TABLE the column position is simply where it is declared.
            $table->unsignedBigInteger('old_id')->nullable();
            $table->unique('old_id');

            $table->foreignId('company_site_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('iban', 50)->nullable();
            $table->string('notes', 191)->nullable();

            $table->timestamps();
        });

        Schema::table('company_sites', function (Blueprint $table) {
            $table->foreign('default_bank_id')->references('id')->on('company_site_banks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('company_sites', function (Blueprint $table) {
            $table->dropForeign(['default_bank_id']);
        });

        Schema::dropIfExists('company_site_banks');
    }
};
