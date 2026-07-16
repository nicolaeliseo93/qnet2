<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Leads\LeadForSelectRequest;
use App\Http\Resources\LeadForSelectResource;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/leads/for-select — minimal, searchable, paginated lead list
 * feeding entity-backed selects (amendment rev.1 A-1, ADR 0011). Feeds the
 * Opportunity create form's "Lead" select (spec 0040).
 *
 * Thin invokable controller: validation (LeadForSelectRequest), server-side
 * authorization (leads.viewAny via LeadPolicy), Service call, paginated
 * response.
 *
 * @see LeadService::forSelect
 */
class LeadForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly LeadService $service) {}

    public function __invoke(LeadForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Lead::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                LeadForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
