<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the `source_id` link from `projects` and `campaigns` (Source
 * relation dropped from the Project/Campaign modules). The `sources` module
 * itself is untouched — it remains a shared resource used elsewhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('source_id')->nullable()->constrained('sources')->nullOnDelete();
        });
    }
};
