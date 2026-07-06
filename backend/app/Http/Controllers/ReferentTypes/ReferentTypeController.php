<?php

namespace App\Http\Controllers\ReferentTypes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ReferentTypes\StoreReferentTypeRequest;
use App\Http\Requests\ReferentTypes\UpdateReferentTypeRequest;
use App\Http\Resources\ReferentTypeResource;
use App\Models\ReferentType;
use App\Models\User;
use App\Services\ReferentTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `referent-types` resource (spec 0016), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ReferentTypePolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see ReferentTypeService
 */
class ReferentTypeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ReferentTypeService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/referent-types/{referentType} — single type (view row-action).
     */
    public function show(Request $request, ReferentType $referentType): JsonResponse
    {
        try {
            $this->authorize('view', $referentType);

            return $this->okWithPermissions(
                new ReferentTypeResource($referentType),
                $this->buildPermissions($request->user(), $referentType),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['referentType' => $referentType->id]);
        }
    }

    /**
     * POST /api/referent-types — create a new type.
     */
    public function store(StoreReferentTypeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ReferentType::class);

            $referentType = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new ReferentTypeResource($referentType),
                $this->buildPermissions($request->user(), $referentType),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/referent-types/{referentType} — update an existing type.
     */
    public function update(UpdateReferentTypeRequest $request, ReferentType $referentType): JsonResponse
    {
        try {
            $this->authorize('update', $referentType);

            $referentType = $this->service->update($referentType, $request->toData());

            return $this->okWithPermissions(
                new ReferentTypeResource($referentType),
                $this->buildPermissions($request->user(), $referentType),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['referentType' => $referentType->id]);
        }
    }

    /**
     * DELETE /api/referent-types/{referentType} — delete a type.
     */
    public function destroy(ReferentType $referentType): JsonResponse
    {
        try {
            $this->authorize('delete', $referentType);

            $this->service->delete($referentType);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['referentType' => $referentType->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?ReferentType $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('referent-types'), $actor, $model);
    }
}
