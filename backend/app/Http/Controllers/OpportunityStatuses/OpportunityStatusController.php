<?php

namespace App\Http\Controllers\OpportunityStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\OpportunityStatuses\StoreOpportunityStatusRequest;
use App\Http\Requests\OpportunityStatuses\UpdateOpportunityStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\OpportunityStatusResource;
use App\Models\OpportunityStatus;
use App\Models\User;
use App\Services\OpportunityStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `opportunity-statuses` resource (spec 0043),
 * backing the backend-driven table row-actions (view/edit/delete) plus
 * create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (OpportunityStatusPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see OpportunityStatusService
 */
class OpportunityStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OpportunityStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/opportunity-statuses/{opportunityStatus} — single opportunity
     * status (view row-action).
     */
    public function show(Request $request, OpportunityStatus $opportunityStatus): JsonResponse
    {
        try {
            $this->authorize('view', $opportunityStatus);

            $opportunityStatus = $this->service->loadDetail($opportunityStatus);

            return $this->okWithPermissions(
                new OpportunityStatusResource($opportunityStatus),
                $this->buildPermissions($request->user(), $opportunityStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunityStatus' => $opportunityStatus->id]);
        }
    }

    /**
     * POST /api/opportunity-statuses — create a new opportunity status.
     */
    public function store(StoreOpportunityStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', OpportunityStatus::class);

            $opportunityStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new OpportunityStatusResource($opportunityStatus),
                $this->buildPermissions($request->user(), $opportunityStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/opportunity-statuses/{opportunityStatus} — update an
     * existing opportunity status.
     */
    public function update(UpdateOpportunityStatusRequest $request, OpportunityStatus $opportunityStatus): JsonResponse
    {
        try {
            $this->authorize('update', $opportunityStatus);

            $opportunityStatus = $this->service->update($opportunityStatus, $request->toData());

            return $this->okWithPermissions(
                new OpportunityStatusResource($opportunityStatus),
                $this->buildPermissions($request->user(), $opportunityStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunityStatus' => $opportunityStatus->id]);
        }
    }

    /**
     * DELETE /api/opportunity-statuses/{opportunityStatus} — delete an
     * opportunity status (BR-2: 409 if referenced by an Opportunity).
     */
    public function destroy(OpportunityStatus $opportunityStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $opportunityStatus);

            $this->service->delete($opportunityStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunityStatus' => $opportunityStatus->id]);
        }
    }

    /**
     * POST /api/opportunity-statuses/reorder — resequence the custom rows
     * (spec 0039, D-5). Gated on `opportunity-statuses.update` directly (no
     * single Model instance exists for a bulk reorder, so there is no Policy
     * `update($user, $model)` to delegate to — mirrors ExportController's
     * `export` ability check).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('opportunity-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (OpportunityStatus $status): array => [
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
    private function buildPermissions(User $actor, ?OpportunityStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('opportunity-statuses'), $actor, $model);
    }
}
