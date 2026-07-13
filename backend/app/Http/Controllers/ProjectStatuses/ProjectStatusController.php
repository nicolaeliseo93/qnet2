<?php

namespace App\Http\Controllers\ProjectStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProjectStatuses\StoreProjectStatusRequest;
use App\Http\Requests\ProjectStatuses\UpdateProjectStatusRequest;
use App\Http\Resources\ProjectStatusResource;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Services\ProjectStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `project-statuses` resource (spec 0023), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ProjectStatusPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see ProjectStatusService
 */
class ProjectStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProjectStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/project-statuses/{projectStatus} — single project status
     * (view row-action).
     */
    public function show(Request $request, ProjectStatus $projectStatus): JsonResponse
    {
        try {
            $this->authorize('view', $projectStatus);

            return $this->okWithPermissions(
                new ProjectStatusResource($projectStatus),
                $this->buildPermissions($request->user(), $projectStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['projectStatus' => $projectStatus->id]);
        }
    }

    /**
     * POST /api/project-statuses — create a new project status.
     */
    public function store(StoreProjectStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ProjectStatus::class);

            $projectStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new ProjectStatusResource($projectStatus),
                $this->buildPermissions($request->user(), $projectStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/project-statuses/{projectStatus} — update an existing
     * project status.
     */
    public function update(UpdateProjectStatusRequest $request, ProjectStatus $projectStatus): JsonResponse
    {
        try {
            $this->authorize('update', $projectStatus);

            $projectStatus = $this->service->update($projectStatus, $request->toData());

            return $this->okWithPermissions(
                new ProjectStatusResource($projectStatus),
                $this->buildPermissions($request->user(), $projectStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['projectStatus' => $projectStatus->id]);
        }
    }

    /**
     * DELETE /api/project-statuses/{projectStatus} — delete a project status
     * (BR-4: 409 if referenced by a Project or a Campaign).
     */
    public function destroy(ProjectStatus $projectStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $projectStatus);

            $this->service->delete($projectStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['projectStatus' => $projectStatus->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?ProjectStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('project-statuses'), $actor, $model);
    }
}
