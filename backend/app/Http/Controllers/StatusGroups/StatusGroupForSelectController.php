<?php

namespace App\Http\Controllers\StatusGroups;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\StatusGroups\StatusGroupForSelectRequest;
use App\Http\Resources\StatusGroupForSelectResource;
use App\Models\StatusGroup;
use App\Services\StatusGroupService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/status-groups/for-select — minimal, searchable, paginated status
 * group list feeding entity-backed selects (spec 0039, ADR 0011 the
 * for-select standard), mirroring LeadStatusForSelectController.
 *
 * Thin invokable controller: validation (StatusGroupForSelectRequest),
 * server-side authorization (status-groups.viewAny via StatusGroupPolicy),
 * Service call, paginated response.
 *
 * @see StatusGroupService::forSelect
 */
class StatusGroupForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly StatusGroupService $service) {}

    public function __invoke(StatusGroupForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', StatusGroup::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                StatusGroupForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
