<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename the shared status lookup from `project_statuses` to `pipeline_statuses`
 * (and the referencing FK columns on `projects`/`campaigns`) to reflect that the
 * pick-list classifies BOTH projects and campaigns, not projects alone. Runs
 * right after the create migrations (which are left untouched); this is the
 * schema half of the full rename to the `pipeline-statuses` module.
 *
 * The referenced table is renamed before the FK columns: the child FK
 * references follow the table/column rename, so the `restrictOnDelete`
 * constraint survives — verified via information_schema after migrate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('project_statuses', 'pipeline_statuses');

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('project_status_id', 'pipeline_status_id');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->renameColumn('project_status_id', 'pipeline_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->renameColumn('pipeline_status_id', 'project_status_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('pipeline_status_id', 'project_status_id');
        });

        Schema::rename('pipeline_statuses', 'project_statuses');
    }
};
