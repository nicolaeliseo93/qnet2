<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the unified import wizard state (spec 0033) to `import_runs`,
 * additively and nullable so the 5 legacy domains (spec 0012) are
 * unaffected: `detected_columns`/`column_mapping`/`global_config` carry the
 * analyze/configure step output, `dedup_strategy` the chosen duplicate
 * handling, the row counters mirror `import_run_rows` staging outcomes and
 * `notified_at` guards the completion notification from firing twice.
 * `error_rows` from the API contract is NOT a new column: it is derived by
 * `ImportRunResource` from the existing `invalid_rows`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->json('detected_columns')->nullable()->after('preview');
            $table->json('column_mapping')->nullable()->after('detected_columns');
            $table->json('global_config')->nullable()->after('column_mapping');
            $table->string('dedup_strategy')->nullable()->after('global_config');
            $table->unsignedInteger('warning_rows')->default(0)->after('dedup_strategy');
            $table->unsignedInteger('duplicate_rows')->default(0)->after('warning_rows');
            $table->unsignedInteger('modified_rows')->default(0)->after('duplicate_rows');
            $table->timestamp('notified_at')->nullable()->after('modified_rows');
            $table->unsignedInteger('error_count')->default(0)->after('notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->dropColumn([
                'detected_columns',
                'column_mapping',
                'global_config',
                'dedup_strategy',
                'warning_rows',
                'duplicate_rows',
                'modified_rows',
                'notified_at',
                'error_count',
            ]);
        });
    }
};
