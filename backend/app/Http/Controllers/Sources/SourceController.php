<?php

namespace App\Http\Controllers\Sources;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Sources\StoreSourceRequest;
use App\Http\Requests\Sources\UpdateSourceRequest;
use App\Http\Resources\SourceResource;
use App\Models\Source;
use App\Models\User;
use App\Services\SourceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `sources` resource (spec 0018), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (SourcePolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see SourceService
 */
class SourceController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SourceService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/sources/{source} — single source (view row-action).
     */
    public function show(Request $request, Source $source): JsonResponse
    {
        try {
            $this->authorize('view', $source);

            return $this->okWithPermissions(
                new SourceResource($source),
                $this->buildPermissions($request->user(), $source),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['source' => $source->id]);
        }
    }

    /**
     * POST /api/sources — create a new source.
     */
    public function store(StoreSourceRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Source::class);

            $source = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new SourceResource($source),
                $this->buildPermissions($request->user(), $source),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/sources/{source} — update an existing source.
     */
    public function update(UpdateSourceRequest $request, Source $source): JsonResponse
    {
        try {
            $this->authorize('update', $source);

            $source = $this->service->update($source, $request->toData());

            return $this->okWithPermissions(
                new SourceResource($source),
                $this->buildPermissions($request->user(), $source),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['source' => $source->id]);
        }
    }

    /**
     * DELETE /api/sources/{source} — delete a source.
     */
    public function destroy(Source $source): JsonResponse
    {
        try {
            $this->authorize('delete', $source);

            $this->service->delete($source);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['source' => $source->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Source $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('sources'), $actor, $model);
    }
}
