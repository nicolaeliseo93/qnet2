<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Per-user UI scale (0..100 slider). Null means "no preference yet" —
            // the resource layer serializes the default (40 => 100% size) instead
            // of null. Held in a tinyint (0..255) which comfortably covers 0..100.
            $table->unsignedTinyInteger('ui_scale')->nullable()->after('module_open_preferences');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ui_scale');
        });
    }
};
