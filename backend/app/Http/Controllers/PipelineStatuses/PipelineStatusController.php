<?php

namespace App\Http\Controllers\PipelineStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\PipelineStatuses\StorePipelineStatusRequest;
use App\Http\Requests\PipelineStatuses\UpdatePipelineStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\PipelineStatusResource;
use App\Models\PipelineStatus;
use App\Models\User;
use App\Services\PipelineStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `pipeline-statuses` resource (spec 0023), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (PipelineStatusPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see PipelineStatusService
 */
class PipelineStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PipelineStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/pipeline-statuses/{pipelineStatus} — single project status
     * (view row-action).
     */
    public function show(Request $request, PipelineStatus $pipelineStatus): JsonResponse
    {
        try {
            $this->authorize('view', $pipelineStatus);

            $pipelineStatus = $this->service->loadDetail($pipelineStatus);

            return $this->okWithPermissions(
                new PipelineStatusResource($pipelineStatus),
                $this->buildPermissions($request->user(), $pipelineStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['pipelineStatus' => $pipelineStatus->id]);
        }
    }

    /**
     * POST /api/pipeline-statuses — create a new project status.
     */
    public function store(StorePipelineStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', PipelineStatus::class);

            $pipelineStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new PipelineStatusResource($pipelineStatus),
                $this->buildPermissions($request->user(), $pipelineStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/pipeline-statuses/{pipelineStatus} — update an existing
     * project status.
     */
    public function update(UpdatePipelineStatusRequest $request, PipelineStatus $pipelineStatus): JsonResponse
    {
        try {
            $this->authorize('update', $pipelineStatus);

            $pipelineStatus = $this->service->update($pipelineStatus, $request->toData());

            return $this->okWithPermissions(
                new PipelineStatusResource($pipelineStatus),
                $this->buildPermissions($request->user(), $pipelineStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['pipelineStatus' => $pipelineStatus->id]);
        }
    }

    /**
     * DELETE /api/pipeline-statuses/{pipelineStatus} — delete a project status
     * (BR-4: 409 if referenced by a Project or a Campaign).
     */
    public function destroy(PipelineStatus $pipelineStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $pipelineStatus);

            $this->service->delete($pipelineStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['pipelineStatus' => $pipelineStatus->id]);
        }
    }

    /**
     * POST /api/pipeline-statuses/reorder — resequence the custom rows (spec
     * 0039, D-5). Gated on `pipeline-statuses.update` directly (no single
     * Model instance exists for a bulk reorder, so there is no Policy
     * `update($user, $model)` to delegate to — mirrors ExportController's
     * `export` ability check).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('pipeline-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (PipelineStatus $status): array => [
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
    private function buildPermissions(User $actor, ?PipelineStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('pipeline-statuses'), $actor, $model);
    }
}
