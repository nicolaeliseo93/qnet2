<?php

namespace App\Http\Controllers\Registries;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Registries\StoreRegistryRequest;
use App\Http\Requests\Registries\UpdateRegistryRequest;
use App\Http\Resources\RegistryResource;
use App\Models\Registry;
use App\Models\User;
use App\Services\RegistryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `registries` resource (spec 0020), backing the
 * backend-driven table row-actions (view/edit/delete) plus create. No
 * for-select endpoint (out of scope — no module selects a registry yet).
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (RegistryPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned registry.
 *
 * @see RegistryService
 */
class RegistryController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RegistryService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/registries/{registry} — single registry (view row-action).
     */
    public function show(Request $request, Registry $registry): JsonResponse
    {
        try {
            $this->authorize('view', $registry);

            return $this->okWithPermissions(
                new RegistryResource($this->service->loadProfileTree($registry)),
                $this->buildPermissions($request->user(), $registry),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['registry' => $registry->id]);
        }
    }

    /**
     * POST /api/registries — create a new registry.
     */
    public function store(StoreRegistryRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Registry::class);

            $registry = $this->service->create($request->user(), $request->toData(), $request->toProfile());

            return $this->okWithPermissions(
                new RegistryResource($registry),
                $this->buildPermissions($request->user(), $registry),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/registries/{registry} — update an existing registry.
     */
    public function update(UpdateRegistryRequest $request, Registry $registry): JsonResponse
    {
        try {
            $this->authorize('update', $registry);

            $registry = $this->service->update($request->user(), $registry, $request->toData(), $request->toProfile());

            return $this->okWithPermissions(
                new RegistryResource($registry),
                $this->buildPermissions($request->user(), $registry),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['registry' => $registry->id]);
        }
    }

    /**
     * DELETE /api/registries/{registry} — delete a registry.
     */
    public function destroy(Registry $registry): JsonResponse
    {
        try {
            $this->authorize('delete', $registry);

            $this->service->delete($registry);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['registry' => $registry->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Registry $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('registries'), $actor, $model);
    }
}
