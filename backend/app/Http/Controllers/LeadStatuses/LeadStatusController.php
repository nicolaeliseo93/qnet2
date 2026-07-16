<?php

namespace App\Http\Controllers\LeadStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\LeadStatuses\StoreLeadStatusRequest;
use App\Http\Requests\LeadStatuses\UpdateLeadStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\LeadStatusResource;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\LeadStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `lead-statuses` resource (spec 0029), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (LeadStatusPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see LeadStatusService
 */
class LeadStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly LeadStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/lead-statuses/{leadStatus} — single lead status (view
     * row-action).
     */
    public function show(Request $request, LeadStatus $leadStatus): JsonResponse
    {
        try {
            $this->authorize('view', $leadStatus);

            $leadStatus = $this->service->loadDetail($leadStatus);

            return $this->okWithPermissions(
                new LeadStatusResource($leadStatus),
                $this->buildPermissions($request->user(), $leadStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['leadStatus' => $leadStatus->id]);
        }
    }

    /**
     * POST /api/lead-statuses — create a new lead status.
     */
    public function store(StoreLeadStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', LeadStatus::class);

            $leadStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new LeadStatusResource($leadStatus),
                $this->buildPermissions($request->user(), $leadStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/lead-statuses/{leadStatus} — update an existing lead
     * status.
     */
    public function update(UpdateLeadStatusRequest $request, LeadStatus $leadStatus): JsonResponse
    {
        try {
            $this->authorize('update', $leadStatus);

            $leadStatus = $this->service->update($leadStatus, $request->toData());

            return $this->okWithPermissions(
                new LeadStatusResource($leadStatus),
                $this->buildPermissions($request->user(), $leadStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['leadStatus' => $leadStatus->id]);
        }
    }

    /**
     * DELETE /api/lead-statuses/{leadStatus} — delete a lead status (BR-3:
     * 409 if referenced by a Lead).
     */
    public function destroy(LeadStatus $leadStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $leadStatus);

            $this->service->delete($leadStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['leadStatus' => $leadStatus->id]);
        }
    }

    /**
     * POST /api/lead-statuses/reorder — resequence the custom rows (spec
     * 0039, D-5). Gated on `lead-statuses.update` directly (no single Model
     * instance exists for a bulk reorder, so there is no Policy
     * `update($user, $model)` to delegate to — mirrors ExportController's
     * `export` ability check).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('lead-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (LeadStatus $status): array => [
                'id' => $status->id,
                'sort_order' => $status->sort_order,
                'system_key' => $status->system_key,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?LeadStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('lead-statuses'), $actor, $model);
    }
}
