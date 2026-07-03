<?php

namespace App\Http\Controllers\BusinessFunctions;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\BusinessFunctions\StoreBusinessFunctionRequest;
use App\Http\Requests\BusinessFunctions\UpdateBusinessFunctionRequest;
use App\Http\Resources\BusinessFunctionResource;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\BusinessFunctionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `business-functions` resource (spec 0010), backing
 * the backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (BusinessFunctionPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see BusinessFunctionService
 */
class BusinessFunctionController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly BusinessFunctionService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/business-functions/{businessFunction} — single function (view row-action).
     */
    public function show(Request $request, BusinessFunction $businessFunction): JsonResponse
    {
        try {
            $this->authorize('view', $businessFunction);

            return $this->okWithPermissions(
                new BusinessFunctionResource($businessFunction),
                $this->buildPermissions($request->user(), $businessFunction),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['businessFunction' => $businessFunction->id]);
        }
    }

    /**
     * POST /api/business-functions — create a new function.
     */
    public function store(StoreBusinessFunctionRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', BusinessFunction::class);

            $businessFunction = $this->service->create($request->user(), $request->toData());

            return $this->okWithPermissions(
                new BusinessFunctionResource($businessFunction),
                $this->buildPermissions($request->user(), $businessFunction),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/business-functions/{businessFunction} — update an existing function.
     */
    public function update(UpdateBusinessFunctionRequest $request, BusinessFunction $businessFunction): JsonResponse
    {
        try {
            $this->authorize('update', $businessFunction);

            $businessFunction = $this->service->update($request->user(), $businessFunction, $request->toData());

            return $this->okWithPermissions(
                new BusinessFunctionResource($businessFunction),
                $this->buildPermissions($request->user(), $businessFunction),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['businessFunction' => $businessFunction->id]);
        }
    }

    /**
     * DELETE /api/business-functions/{businessFunction} — delete a function.
     */
    public function destroy(BusinessFunction $businessFunction): JsonResponse
    {
        try {
            $this->authorize('delete', $businessFunction);

            $this->service->delete($businessFunction);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['businessFunction' => $businessFunction->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?BusinessFunction $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('business-functions'), $actor, $model);
    }
}
