<?php

namespace App\Services;

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for kicking off an external-data migration (spec 0013,
 * phase 2): create the MigrationRun (status=pending) and dispatch
 * RunMigrationJob to page through the source in the background. Mirrors
 * ImportService::start.
 */
class MigrationService
{
    public function start(User $actor, string $source): MigrationRun
    {
        /** @var MigrationRun $run */
        $run = DB::transaction(fn (): MigrationRun => MigrationRun::create([
            'source' => $source,
            'user_id' => $actor->id,
            'status' => MigrationStatus::Pending,
            'total_rows' => 0,
            'created_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'report' => null,
        ]));

        RunMigrationJob::dispatch($run->id);

        return $run;
    }
}
