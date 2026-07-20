<?php

namespace App\Http\Controllers\OpportunityStatuses;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\OpportunityStatuses\OpportunityStatusForSelectRequest;
use App\Http\Resources\OpportunityStatusForSelectResource;
use App\Models\OpportunityStatus;
use App\Services\OpportunityStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/opportunity-statuses/for-select — minimal, searchable, paginated
 * opportunity status list feeding entity-backed selects (spec 0043, ADR 0011
 * the for-select standard).
 *
 * Thin invokable controller: validation (OpportunityStatusForSelectRequest),
 * server-side authorization (opportunity-statuses.viewAny via
 * OpportunityStatusPolicy), Service call, paginated response.
 *
 * @see OpportunityStatusService::forSelect
 */
class OpportunityStatusForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly OpportunityStatusService $service) {}

    public function __invoke(OpportunityStatusForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', OpportunityStatus::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                OpportunityStatusForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
