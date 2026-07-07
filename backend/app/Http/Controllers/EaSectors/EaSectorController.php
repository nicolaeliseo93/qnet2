<?php

namespace App\Http\Controllers\EaSectors;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EaSectors\StoreEaSectorRequest;
use App\Http\Requests\EaSectors\UpdateEaSectorRequest;
use App\Http\Resources\EaSectorResource;
use App\Models\EaSector;
use App\Models\User;
use App\Services\EaSectorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD + tree endpoints for the `ea-sectors` resource (spec 0018), backing
 * the backend-driven table row-actions plus the dedicated tree view for the
 * future parent picker.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (EaSectorPolicy), Service call, response. No business logic, no queries.
 *
 * @see EaSectorService
 */
class EaSectorController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EaSectorService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/ea-sectors/tree — the full nested tree (roots first).
     */
    public function tree(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', EaSector::class);

            return $this->ok($this->service->tree());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/ea-sectors/{eaSector} — single sector (view row-action).
     */
    public function show(Request $request, EaSector $eaSector): JsonResponse
    {
        try {
            $this->authorize('view', $eaSector);

            return $this->okWithPermissions(
                (new EaSectorResource($eaSector))->resolve(),
                $this->buildPermissions($request->user(), $eaSector),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['eaSector' => $eaSector->id]);
        }
    }

    /**
     * POST /api/ea-sectors — create a new sector.
     */
    public function store(StoreEaSectorRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', EaSector::class);

            $eaSector = $this->service->create($request->toData());

            return $this->okWithPermissions(
                (new EaSectorResource($eaSector))->resolve(),
                $this->buildPermissions($request->user(), $eaSector),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/ea-sectors/{eaSector} — update an existing sector.
     */
    public function update(UpdateEaSectorRequest $request, EaSector $eaSector): JsonResponse
    {
        try {
            $this->authorize('update', $eaSector);

            $eaSector = $this->service->update($eaSector, $request->toData());

            return $this->okWithPermissions(
                (new EaSectorResource($eaSector))->resolve(),
                $this->buildPermissions($request->user(), $eaSector),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['eaSector' => $eaSector->id]);
        }
    }

    /**
     * DELETE /api/ea-sectors/{eaSector} — delete a sector.
     */
    public function destroy(EaSector $eaSector): JsonResponse
    {
        try {
            $this->authorize('delete', $eaSector);

            $this->service->delete($eaSector);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['eaSector' => $eaSector->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?EaSector $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('ea-sectors'), $actor, $model);
    }
}
