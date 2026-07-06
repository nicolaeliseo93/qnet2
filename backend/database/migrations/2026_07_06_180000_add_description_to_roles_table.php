<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `description` to `roles` (spec 0013 — external data migration): the
 * external system carries a human description alongside each role name, which
 * qnet's roles table did not previously store. Purely additive and nullable —
 * every existing role keeps a null description; RolesSource fills it on import
 * (spatie/laravel-permission's Role model reads it as a plain attribute).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
