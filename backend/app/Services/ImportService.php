<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Imports\ImportDefinition;
use App\Imports\RowOutcome;
use App\Jobs\ProcessImportJob;
use App\Jobs\ValidateImportJob;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Business logic for the generic CSV import engine (spec 0012): create the
 * ImportRun (phase 1 dispatch), validate the confirm transition (phase 2
 * dispatch), and write the downloadable errors CSV report. The controller
 * stays thin; this Service is the single authority — mirrors
 * AttachmentService's disk-write conventions (private disk, uuid path, never
 * the client's filename).
 */
class ImportService
{
    private const string DISK = 'local';

    private const string DIRECTORY = 'imports';

    /**
     * Store the uploaded file, create the ImportRun (status=validating) and
     * dispatch the dry-run validation job.
     */
    public function start(User $actor, ImportDefinition $definition, UploadedFile $file): ImportRun
    {
        $storedPath = $this->storeUpload($file);

        // The 3 counters are passed explicitly (not left to the DB column
        // default) so the IN-MEMORY model returned here already reflects them
        // — the caller (ImportController::upload) serializes this same
        // instance via ImportRunResource without an extra round-trip.
        /** @var ImportRun $run */
        $run = DB::transaction(fn (): ImportRun => ImportRun::create([
            'resource' => $definition->resource(),
            'user_id' => $actor->id,
            'status' => ImportStatus::Validating,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'total_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
        ]));

        ValidateImportJob::dispatch($run->id);

        return $run;
    }

    /**
     * Move an awaiting_confirmation run to processing and dispatch the commit
     * job. Any other current status is rejected with a 422 (via abort(),
     * mapped by BaseApiController::handleControllerException — same
     * convention as RoleService/RoleAssignmentGuard).
     */
    public function confirm(ImportRun $run): ImportRun
    {
        if ($run->status !== ImportStatus::AwaitingConfirmation) {
            abort(422, 'The import cannot be confirmed in its current status.');
        }

        $run->update(['status' => ImportStatus::Processing]);

        ProcessImportJob::dispatch($run->id);

        return $run->fresh();
    }

    /**
     * Write the errors CSV report for the given rejected rows (the FULL set,
     * not just the preview sample) and persist its path on the run. Header =
     * the definition's template columns + row_number + errors (spec 0012
     * data_contract — GET .../errors).
     *
     * @param  array<int, string>  $columns
     * @param  array<int, RowOutcome>  $rejectedRows
     */
    public function writeErrorReport(ImportRun $run, array $columns, array $rejectedRows): void
    {
        $handle = fopen('php://temp', 'w+');

        fputcsv($handle, [...$columns, 'row_number', 'errors']);

        foreach ($rejectedRows as $outcome) {
            fputcsv($handle, [
                ...array_map(static fn (string $column): string => $outcome->values[$column] ?? '', $columns),
                $outcome->rowNumber,
                implode('; ', $outcome->errors),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $path = self::DIRECTORY.'/'.Str::uuid().'-errors.csv';
        Storage::disk(self::DISK)->put($path, $csv);

        $run->update(['error_report_path' => $path]);
    }

    /**
     * Store the uploaded file on the private `local` disk. The stored object
     * name is a random UUID (the client's original name is never used as a
     * path, preventing traversal and collisions); the original name is kept
     * only as ImportRun::original_filename metadata.
     */
    private function storeUpload(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $storedName = (string) Str::uuid().($extension !== '' ? '.'.$extension : '');

        $path = Storage::disk(self::DISK)->putFileAs(self::DIRECTORY, $file, $storedName);

        if ($path === false) {
            abort(500, 'Failed to store the uploaded file.');
        }

        return $path;
    }
}
