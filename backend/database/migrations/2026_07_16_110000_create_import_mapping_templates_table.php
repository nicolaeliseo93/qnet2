<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable, team-shared column mapping saved from a completed wizard
 * configure step (spec 0035). `columns` is the ordered column key snapshot
 * (App\Imports\ColumnAnalysis::columnKeys()) used for exact-match detection
 * against a newly uploaded file's `detected_columns`; `resource` mirrors
 * `import_runs.resource` (the `{domain}` key), not a foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mapping_templates', function (Blueprint $table) {
            $table->id();
            $table->string('resource')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('columns');
            $table->json('column_mapping');
            $table->string('dedup_strategy')->nullable();
            $table->timestamps();

            $table->unique(['resource', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping_templates');
    }
};
