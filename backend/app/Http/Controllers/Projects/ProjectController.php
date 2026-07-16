<?php

namespace App\Http\Controllers\Projects;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Projects\ProjectIndexRequest;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Http\Resources\ProjectCardResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `projects` resource (spec 0023), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ProjectPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned project.
 *
 * @see ProjectService
 */
class ProjectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProjectService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/projects — card-grid list (spec 0026, D-3): paginated,
     * searchable, status-filterable, carrying the per-card campaigns/leads
     * counts and can.update/can.delete affordances (BR-2).
     */
    public function index(ProjectIndexRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Project::class);

            $result = $this->service->index($request->toData());

            return $this->paginatedResponse(
                ProjectCardResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/projects/{project} — single project (view row-action).
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        try {
            $this->authorize('view', $project);

            return $this->okWithPermissions(
                new ProjectResource($this->service->loadDetail($project)),
                $this->buildPermissions($request->user(), $project),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['project' => $project->id]);
        }
    }

    /**
     * GET /api/projects/next-code — the next sequential code (PRJ-0001...) as
     * a non-binding suggestion for the create form's auto-fill (spec 0025).
     * Gated by projects.create: only an actor who may create needs it.
     */
    public function nextCode(): JsonResponse
    {
        try {
            $this->authorize('create', Project::class);

            return $this->ok(['code' => $this->service->previewNextCode()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/projects — create a new project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Project::class);

            $project = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new ProjectResource($project),
                $this->buildPermissions($request->user(), $project),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/projects/{project} — update an existing project.
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        try {
            $this->authorize('update', $project);

            $project = $this->service->update($project, $request->toData());

            return $this->okWithPermissions(
                new ProjectResource($project),
                $this->buildPermissions($request->user(), $project),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['project' => $project->id]);
        }
    }

    /**
     * DELETE /api/projects/{project} — delete a project (BR-5: 409 if it has
     * campaigns).
     */
    public function destroy(Project $project): JsonResponse
    {
        try {
            $this->authorize('delete', $project);

            $this->service->delete($project);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['project' => $project->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Project $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('projects'), $actor, $model);
    }
}
