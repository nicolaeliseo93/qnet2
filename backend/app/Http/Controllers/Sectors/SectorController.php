<?php

namespace App\Http\Controllers\Sectors;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Sectors\StoreSectorRequest;
use App\Http\Requests\Sectors\UpdateSectorRequest;
use App\Http\Resources\SectorResource;
use App\Models\Sector;
use App\Models\User;
use App\Services\SectorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD + tree endpoints for the `sectors` resource (spec 0018), backing
 * the backend-driven table row-actions plus the dedicated tree view for the
 * future parent picker.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (SectorPolicy), Service call, response. No business logic, no queries.
 *
 * @see SectorService
 */
class SectorController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SectorService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/sectors/tree — the full nested tree (roots first).
     */
    public function tree(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Sector::class);

            return $this->ok($this->service->tree());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/sectors/{sector} — single sector (view row-action).
     */
    public function show(Request $request, Sector $sector): JsonResponse
    {
        try {
            $this->authorize('view', $sector);

            return $this->okWithPermissions(
                (new SectorResource($sector))->resolve(),
                $this->buildPermissions($request->user(), $sector),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['sector' => $sector->id]);
        }
    }

    /**
     * POST /api/sectors — create a new sector.
     */
    public function store(StoreSectorRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Sector::class);

            $sector = $this->service->create($request->toData());

            return $this->okWithPermissions(
                (new SectorResource($sector))->resolve(),
                $this->buildPermissions($request->user(), $sector),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/sectors/{sector} — update an existing sector.
     */
    public function update(UpdateSectorRequest $request, Sector $sector): JsonResponse
    {
        try {
            $this->authorize('update', $sector);

            $sector = $this->service->update($sector, $request->toData());

            return $this->okWithPermissions(
                (new SectorResource($sector))->resolve(),
                $this->buildPermissions($request->user(), $sector),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['sector' => $sector->id]);
        }
    }

    /**
     * DELETE /api/sectors/{sector} — delete a sector.
     */
    public function destroy(Sector $sector): JsonResponse
    {
        try {
            $this->authorize('delete', $sector);

            $this->service->delete($sector);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['sector' => $sector->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Sector $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('sectors'), $actor, $model);
    }
}
