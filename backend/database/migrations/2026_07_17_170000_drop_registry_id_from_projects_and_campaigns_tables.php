<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the `registry_id` link from `projects` and `campaigns` (Client
 * relation dropped from the Project/Campaign modules). The `registries`
 * module itself is untouched; leads keep their own `registry_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // The FK constraint must be dropped BEFORE the explicit index:
            // MySQL refuses to drop an index still backing an active FK
            // (error 1553).
            $table->dropForeign(['registry_id']);
            $table->dropIndex(['registry_id']);
            $table->dropColumn('registry_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registry_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('registry_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->index('registry_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('registry_id')->nullable()->constrained('registries')->nullOnDelete();
        });
    }
};
