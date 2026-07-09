<?php

namespace App\Http\Controllers\CustomFields;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CustomFields\StoreCustomFieldRequest;
use App\Http\Requests\CustomFields\UpdateCustomFieldRequest;
use App\Http\Resources\CustomFieldResource;
use App\Models\CustomFieldDefinition;
use App\Models\User;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `custom-fields` admin resource (spec 0021 — ADMIN
 * CRUD DEFINIZIONI): defines/manages the CustomFieldDefinition catalogue that
 * every custom-fieldable module reads through App\CustomFields\CustomFieldProvider.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (CustomFieldDefinitionPolicy), Service call, response. No business logic,
 * no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see CustomFieldService
 */
class CustomFieldController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CustomFieldService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/custom-fields/{customField} — single definition (view row-action).
     */
    public function show(Request $request, CustomFieldDefinition $customField): JsonResponse
    {
        try {
            $this->authorize('view', $customField);

            return $this->okWithPermissions(
                new CustomFieldResource($customField->loadMissing('options')),
                $this->buildPermissions($request->user(), $customField),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['customField' => $customField->id]);
        }
    }

    /**
     * POST /api/custom-fields — create a new definition.
     */
    public function store(StoreCustomFieldRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', CustomFieldDefinition::class);

            $customField = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new CustomFieldResource($customField),
                $this->buildPermissions($request->user(), $customField),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/custom-fields/{customField} — update an existing definition.
     */
    public function update(UpdateCustomFieldRequest $request, CustomFieldDefinition $customField): JsonResponse
    {
        try {
            $this->authorize('update', $customField);

            $customField = $this->service->update($customField, $request->toData());

            return $this->okWithPermissions(
                new CustomFieldResource($customField),
                $this->buildPermissions($request->user(), $customField),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['customField' => $customField->id]);
        }
    }

    /**
     * DELETE /api/custom-fields/{customField} — delete a definition.
     */
    public function destroy(CustomFieldDefinition $customField): JsonResponse
    {
        try {
            $this->authorize('delete', $customField);

            $this->service->delete($customField);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['customField' => $customField->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?CustomFieldDefinition $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('custom-fields'), $actor, $model);
    }
}
