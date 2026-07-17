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
            // Per-user module open mode preference (spec 0042): { mode, overrides }.
            // Null means "no preference yet" — the resource layer serializes the
            // default { mode: 'custom', overrides: {} } instead of null.
            $table->json('module_open_preferences')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('module_open_preferences');
        });
    }
};
