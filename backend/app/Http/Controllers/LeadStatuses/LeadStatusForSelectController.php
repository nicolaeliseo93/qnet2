<?php

namespace App\Http\Controllers\LeadStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\LeadStatuses\LeadStatusForSelectRequest;
use App\Http\Resources\LeadStatusForSelectResource;
use App\Models\LeadStatus;
use App\Services\LeadStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/lead-statuses/for-select — minimal, searchable, paginated lead
 * status list feeding entity-backed selects (spec 0029, ADR 0011 the
 * for-select standard), mirroring ProjectStatusForSelectController.
 *
 * Thin invokable controller: validation (LeadStatusForSelectRequest),
 * server-side authorization (lead-statuses.viewAny via LeadStatusPolicy),
 * Service call, paginated response.
 *
 * @see LeadStatusService::forSelect
 */
class LeadStatusForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly LeadStatusService $service) {}

    public function __invoke(LeadStatusForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', LeadStatus::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                LeadStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
