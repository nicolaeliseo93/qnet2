<?php

namespace App\Jobs;

use App\Enums\MigrationStatus;
use App\Models\MassMigrationRun;
use App\Models\MigrationRun;
use App\Services\MigrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * The "Import all" orchestrator (spec 0046): run every enabled source of a
 * MassMigrationRun IN THE PLANNED ORDER, one child MigrationRun each, delegating
 * the actual import to MigrationService::runSource (the same core as the
 * single-source job). Later phases depend on earlier ones via `old_id`, so the
 * FIRST failing source STOPS the chain — the not-yet-reached sources never run.
 * The failure is a domain outcome observed via polling, so the job does not
 * re-throw.
 */
class RunMassMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $massMigrationRunId) {}

    public function handle(MigrationService $service): void
    {
        // A full migration can exceed PHP's max_execution_time; under the sync
        // queue driver this runs inside the HTTP request (see RunMigrationJob).
        set_time_limit(0);

        /** @var MassMigrationRun $massRun */
        $massRun = MassMigrationRun::query()->findOrFail($this->massMigrationRunId);

        $massRun->update(['status' => MigrationStatus::Processing]);

        // Step 1: run each source in order; the first failure stops the chain.
        foreach ($massRun->sources as $source) {
            $child = $this->makeChildRun($massRun, (string) $source);

            try {
                $service->runSource($child);
            } catch (Throwable) {
                // runSource already moved the child to `failed`; stop here so the
                // dependent, not-yet-reached sources never run against a partial
                // parent.
                $massRun->update(['status' => MigrationStatus::Failed]);

                return;
            }
        }

        // Step 2: every source completed.
        $massRun->update(['status' => MigrationStatus::Completed]);
    }

    private function makeChildRun(MassMigrationRun $massRun, string $source): MigrationRun
    {
        return MigrationRun::create([
            'source' => $source,
            'user_id' => $massRun->user_id,
            'mass_migration_run_id' => $massRun->id,
            'status' => MigrationStatus::Pending,
            'total_rows' => 0,
            'created_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'report' => null,
        ]);
    }
}
