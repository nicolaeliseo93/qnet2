<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Migration\MigrationPreviewRequest;
use App\Http\Resources\Migration\MigrationRunResource;
use App\Http\Resources\Migration\MigrationSourceResource;
use App\Migrations\MigrationQuery;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationRun;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Generic, registry-driven external-data migration endpoints (spec 0013).
 * One controller serves every source; {source} resolves the MigrationSource
 * through the registry (unknown -> 404), mirroring App\Http\Controllers\
 * Table\TableController / Import\ImportController. Authorization is a single
 * hard gate applied at the route level (the `super-admin` middleware alias —
 * EnsureSuperAdmin); a bound {migrationRun} that does not belong to the actor
 * OR whose source does not match {source} 404s (never 403), mirroring
 * ImportController::assertOwnedRun.
 *
 * @see MigrationService
 */
class MigrationController extends BaseApiController
{
    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly MigrationService $service,
    ) {}

    /**
     * GET /api/migrations — the registered sources for the picker.
     */
    public function index(): JsonResponse
    {
        try {
            return $this->ok(['sources' => MigrationSourceResource::collection($this->registry->all())]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/migrations/{source}/columns — the source's preview column
     * catalogue.
     */
    public function columns(string $source): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($source); // 404 if unknown

            return $this->ok(['columns' => $definition->columns()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/migrations/{source}/preview — phase 1, read-only paginated
     * anteprima of the external system.
     */
    public function preview(MigrationPreviewRequest $request, string $source): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($source); // 404 if unknown

            $page = $definition->preview(new MigrationQuery(
                page: $request->pageNumber(),
                perPage: $request->perPageSize(),
            ));

            return $this->ok([
                'rows' => $page->rows,
                'pagination' => [
                    'page' => $page->page,
                    'per_page' => $page->perPage,
                    'total' => $page->total,
                    'has_more' => $page->hasMore,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/migrations/{source}/import — phase 2, creates the
     * MigrationRun (pending) and dispatches the background job.
     */
    public function import(Request $request, string $source): JsonResponse
    {
        try {
            $this->registry->resolve($source); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();

            $run = $this->service->start($actor, $source);

            return $this->created(['migration_run' => new MigrationRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/migrations/{source}/runs/{migrationRun} — polling: status +
     * counters + report.
     */
    public function run(Request $request, string $source, MigrationRun $migrationRun): JsonResponse
    {
        try {
            $this->registry->resolve($source); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->assertOwnedRun($migrationRun, $actor, $source);

            return $this->ok(['migration_run' => (new MigrationRunResource($migrationRun))->withReport()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['migrationRun' => $migrationRun->id]);
        }
    }

    /**
     * A bound {migrationRun} that is not owned by the actor, or whose source
     * does not match the route {source}, must never leak cross-user/cross-
     * source: surfaced as 404 (not 403), identical to an unknown id.
     *
     * @throws ModelNotFoundException
     */
    private function assertOwnedRun(MigrationRun $migrationRun, User $actor, string $source): void
    {
        if ($migrationRun->user_id !== $actor->id || $migrationRun->source !== $source) {
            throw (new ModelNotFoundException)->setModel(MigrationRun::class, [$migrationRun->id]);
        }
    }
}
