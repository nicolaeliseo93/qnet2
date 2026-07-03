<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `old_id` to `roles` (spec 0013 — external data migration): the
 * external system's id for a role migrated from it. RolesSource adopts
 * `old_id` onto an existing role sharing the same `name` instead of
 * duplicating it, so this stays nullable/unique like the other four
 * migrated tables. Purely additive: no existing column is changed
 * (spatie/laravel-permission's Role model reads it as a plain attribute).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->after('id');
            $table->unique('old_id');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
