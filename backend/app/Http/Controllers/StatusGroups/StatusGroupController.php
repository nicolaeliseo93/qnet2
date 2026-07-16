<?php

namespace App\Http\Controllers\StatusGroups;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\StatusGroups\StoreStatusGroupRequest;
use App\Http\Requests\StatusGroups\UpdateStatusGroupRequest;
use App\Http\Resources\StatusGroupResource;
use App\Models\StatusGroup;
use App\Models\User;
use App\Services\StatusGroupService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `status-groups` resource (spec 0039), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (StatusGroupPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see StatusGroupService
 */
class StatusGroupController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly StatusGroupService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/status-groups/{statusGroup} — single status group (view
     * row-action).
     */
    public function show(Request $request, StatusGroup $statusGroup): JsonResponse
    {
        try {
            $this->authorize('view', $statusGroup);

            return $this->okWithPermissions(
                new StatusGroupResource($statusGroup),
                $this->buildPermissions($request->user(), $statusGroup),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['statusGroup' => $statusGroup->id]);
        }
    }

    /**
     * POST /api/status-groups — create a new status group.
     */
    public function store(StoreStatusGroupRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', StatusGroup::class);

            $statusGroup = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new StatusGroupResource($statusGroup),
                $this->buildPermissions($request->user(), $statusGroup),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/status-groups/{statusGroup} — update an existing status
     * group.
     */
    public function update(UpdateStatusGroupRequest $request, StatusGroup $statusGroup): JsonResponse
    {
        try {
            $this->authorize('update', $statusGroup);

            $statusGroup = $this->service->update($statusGroup, $request->toData());

            return $this->okWithPermissions(
                new StatusGroupResource($statusGroup),
                $this->buildPermissions($request->user(), $statusGroup),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['statusGroup' => $statusGroup->id]);
        }
    }

    /**
     * DELETE /api/status-groups/{statusGroup} — delete a status group (409
     * if referenced by a pipeline status or a lead status).
     */
    public function destroy(StatusGroup $statusGroup): JsonResponse
    {
        try {
            $this->authorize('delete', $statusGroup);

            $this->service->delete($statusGroup);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['statusGroup' => $statusGroup->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?StatusGroup $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('status-groups'), $actor, $model);
    }
}
