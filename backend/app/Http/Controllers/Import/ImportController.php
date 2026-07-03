<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Import\UploadImportRequest;
use App\Http\Resources\ImportRunResource;
use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Generic, domain-driven CSV import endpoints (spec 0012). One controller
 * serves every domain; {domain} resolves the ImportDefinition through the
 * registry (unknown → 404), mirroring App\Http\Controllers\Table\
 * TableController. Every action re-authorizes via the definition's
 * authorizeImport() (deny → 403); a bound {importRun} that does not belong to
 * the actor OR whose resource does not match {domain} 404s (never 403),
 * mirroring TableFilterViewController::assertBelongsToDomain.
 *
 * @see ImportService
 */
class ImportController extends BaseApiController
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportService $service,
    ) {}

    /**
     * GET /api/imports/{domain}/template — downloadable CSV template, header =
     * the definition's declared columns, in order.
     */
    public function template(Request $request, string $domain): StreamedResponse|JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());

            $columns = $definition->columnIds();

            return response()->streamDownload(function () use ($columns): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $columns);
                fclose($handle);
            }, "{$domain}-import-template.csv", ['Content-Type' => 'text/csv']);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/imports/{domain} — store the uploaded file, create the
     * ImportRun (status=validating) and dispatch the dry-run validation job.
     */
    public function upload(UploadImportRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeImport($definition, $actor);

            $run = $this->service->start($actor, $definition, $request->file('file'));

            return $this->created(['import_run' => new ImportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/imports/{domain}/{importRun} — poll the run's status and (from
     * awaiting_confirmation onward) its bounded preview.
     */
    public function show(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);

            return $this->ok([
                'import_run' => new ImportRunResource($importRun),
                'preview' => $importRun->preview,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * POST /api/imports/{domain}/{importRun}/confirm — move an
     * awaiting_confirmation run to processing and dispatch the commit job
     * (any other status → 422, via ImportService::confirm).
     */
    public function confirm(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);

            $run = $this->service->confirm($importRun);

            return $this->ok(['import_run' => new ImportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * GET /api/imports/{domain}/{importRun}/errors — downloadable CSV of every
     * rejected row (not just the preview sample); 404 when no report exists.
     */
    public function errors(Request $request, string $domain, ImportRun $importRun): StreamedResponse|JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->assertHasErrorReport($importRun);

            return Storage::disk('local')->download(
                $importRun->error_report_path,
                "{$domain}-import-errors-{$importRun->id}.csv",
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * Single enforcement point: deny → AuthorizationException → 403.
     *
     * @throws AuthorizationException
     */
    private function authorizeImport(ImportDefinition $definition, User $actor): void
    {
        if (! $definition->authorizeImport($actor)) {
            throw new AuthorizationException;
        }
    }

    /**
     * A bound {importRun} that is not owned by the actor, or whose resource
     * does not match the route {domain}, must never leak cross-user/cross-
     * domain: surfaced as 404 (not 403), identical to an unknown id.
     *
     * @throws ModelNotFoundException
     */
    private function assertOwnedRun(ImportRun $importRun, User $actor, string $domain): void
    {
        if ($importRun->user_id !== $actor->id || $importRun->resource !== $domain) {
            throw (new ModelNotFoundException)->setModel(ImportRun::class, [$importRun->id]);
        }
    }

    /**
     * @throws ModelNotFoundException when no errors report was ever written.
     */
    private function assertHasErrorReport(ImportRun $importRun): void
    {
        if ($importRun->error_report_path === null || ! Storage::disk('local')->exists($importRun->error_report_path)) {
            throw (new ModelNotFoundException)->setModel(ImportRun::class, [$importRun->id]);
        }
    }
}
