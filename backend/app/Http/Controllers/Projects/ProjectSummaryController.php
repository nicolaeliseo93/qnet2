<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/projects/summary — the 3 KPI tiles feeding the projects card grid
 * (spec 0026): projects_count/campaigns_count/leads_count.
 *
 * Thin invokable controller, mirroring ProjectForSelectController:
 * server-side authorization (projects.viewAny via ProjectPolicy), Service
 * call, plain ok() envelope (no pagination here).
 *
 * @see ProjectService::summary
 */
class ProjectSummaryController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ProjectService $service) {}

    public function __invoke(): JsonResponse
    {
        try {
            $this->authorize('viewAny', Project::class);

            return $this->ok($this->service->summary());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
