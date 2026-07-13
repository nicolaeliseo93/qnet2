<?php

namespace App\Http\Controllers\ProjectStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProjectStatuses\ProjectStatusForSelectRequest;
use App\Http\Resources\ProjectStatusForSelectResource;
use App\Models\ProjectStatus;
use App\Services\ProjectStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/project-statuses/for-select — minimal, searchable, paginated
 * project status list feeding entity-backed selects (spec 0023, ADR 0011 the
 * for-select standard), mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation (ProjectStatusForSelectRequest),
 * server-side authorization (project-statuses.viewAny via
 * ProjectStatusPolicy), Service call, paginated response.
 *
 * @see ProjectStatusService::forSelect
 */
class ProjectStatusForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ProjectStatusService $service) {}

    public function __invoke(ProjectStatusForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', ProjectStatus::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ProjectStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
