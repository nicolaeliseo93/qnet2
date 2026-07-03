<?php

namespace App\Http\Controllers\Roles;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the Roles resource, backing the backend-driven table
 * row-actions (view / edit / delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (RolePolicy), Service call, response. No business logic, no queries.
 *
 * Authorization is re-enforced server-side on every action because these routes
 * are hit by frontend row-actions, which are NOT the source of truth.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned role.
 *
 * @see RoleService
 */
class RoleController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RoleService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/roles/{role} — single role (view row-action).
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        try {
            $this->authorize('view', $role);

            return $this->okWithPermissions(new RoleResource($role), $this->buildPermissions($request->user(), $role));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['role' => $role->id]);
        }
    }

    /**
     * POST /api/roles — create a new role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Role::class);

            $role = $this->service->create($request->user(), $request->toData());

            return $this->okWithPermissions(
                new RoleResource($role),
                $this->buildPermissions($request->user(), $role),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/roles/{role} — update an existing role (edit row-action).
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        try {
            $this->authorize('update', $role);

            $role = $this->service->update($request->user(), $role, $request->toData());

            return $this->okWithPermissions(new RoleResource($role), $this->buildPermissions($request->user(), $role));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['role' => $role->id]);
        }
    }

    /**
     * DELETE /api/roles/{role} — delete a role (delete row-action).
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            $this->authorize('delete', $role);

            $this->service->delete($role);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['role' => $role->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Role $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('roles'), $actor, $model);
    }
}
