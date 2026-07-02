<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the single-primary flag to the polymorphic address module (ADR 0010).
 *
 * `is_primary` marks the preferred address of an owner. The invariant "at most
 * one primary per owner" is enforced in AddressService (no DB-level constraint
 * is possible on a polymorphic relation), but the composite index keeps the
 * demote-siblings lookup (addressable_type + addressable_id + is_primary) cheap.
 *
 * No backfill of existing rows: out of scope per the ADR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('addressable_id');

            $table->index(
                ['addressable_type', 'addressable_id', 'is_primary'],
                'addresses_owner_primary_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_owner_primary_index');
            $table->dropColumn('is_primary');
        });
    }
};
