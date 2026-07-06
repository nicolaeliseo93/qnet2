<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Models\ExportRun;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Async generation of a single export run (spec 0014): resolves the frozen
 * run, delegates the actual query/writer work to ExportService, and on any
 * unhandled failure moves the run to `failed` instead of leaving it stuck in
 * `processing` — mirrors ValidateImportJob/ProcessImportJob.
 */
class GenerateExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $exportRunId) {}

    public function handle(ExportService $service): void
    {
        // A large export can exceed PHP's max_execution_time; under the sync
        // queue driver this job runs inside the HTTP request and would be
        // killed at 30s. Lift the per-script limit (harmless under async
        // workers, which govern their own timeout).
        set_time_limit(0);

        /** @var ExportRun $run */
        $run = ExportRun::query()->findOrFail($this->exportRunId);

        try {
            $service->generate($run);
        } catch (Throwable $exception) {
            $run->update(['status' => ExportStatus::Failed]);

            throw $exception;
        }
    }
}
