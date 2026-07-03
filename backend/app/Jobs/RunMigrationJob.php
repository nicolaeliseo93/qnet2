<?php

namespace App\Jobs;

use App\Enums\MigrationStatus;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Phase 2 (background import, spec 0013): resolve the run's MigrationSource
 * and page through the ENTIRE external source, creating every valid row
 * (idempotent per `old_id`, isolated per-row transactions — see
 * AbstractMigrationSource::import). Moves the run pending -> processing ->
 * completed; on any unhandled failure the run moves to `failed` instead of
 * staying stuck mid-flight (mirrors ProcessImportJob).
 */
class RunMigrationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $migrationRunId) {}

    public function handle(MigrationRegistry $registry): void
    {
        /** @var MigrationRun $run */
        $run = MigrationRun::query()->findOrFail($this->migrationRunId);

        try {
            $run->update(['status' => MigrationStatus::Processing]);

            $source = $registry->resolve($run->source);
            /** @var User $actor */
            $actor = User::query()->findOrFail($run->user_id);

            $source->import(new MigrationImportContext(run: $run, actor: $actor));

            $run->update(['status' => MigrationStatus::Completed]);
        } catch (Throwable $exception) {
            $run->update(['status' => MigrationStatus::Failed]);

            throw $exception;
        }
    }
}
