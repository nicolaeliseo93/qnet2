<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Projects\ProjectForSelectRequest;
use App\Http\Resources\ProjectForSelectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/projects/for-select — minimal, searchable, paginated project list
 * feeding entity-backed selects (spec 0023, ADR 0011), mirroring
 * ReferentForSelectController. `meta` carries the Campaign form's defaults
 * (registry/source/partner/project_status/business_function/state/
 * product_category + budget figures).
 *
 * Thin invokable controller: validation (ProjectForSelectRequest),
 * server-side authorization (projects.viewAny via ProjectPolicy), Service
 * call, paginated response.
 *
 * @see ProjectService::forSelect
 */
class ProjectForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ProjectService $service) {}

    public function __invoke(ProjectForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Project::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ProjectForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
