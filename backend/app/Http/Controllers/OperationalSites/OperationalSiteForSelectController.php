<?php

namespace App\Http\Controllers\OperationalSites;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\OperationalSites\OperationalSiteForSelectRequest;
use App\Http\Resources\OperationalSiteForSelectResource;
use App\Models\OperationalSite;
use App\Services\OperationalSiteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/operational-sites/for-select — minimal, searchable, paginated
 * operational-site list feeding the user-form "site" select (spec 0015,
 * ADR 0011 the for-select standard), mirroring UserForSelectController.
 *
 * Thin invokable controller: validation (OperationalSiteForSelectRequest),
 * server-side authorization (operational-sites.viewAny via
 * OperationalSitePolicy), Service call, paginated response. The query/search/
 * hydration logic lives in OperationalSiteService::forSelect, not here.
 *
 * @see OperationalSiteService::forSelect
 */
class OperationalSiteForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly OperationalSiteService $service) {}

    public function __invoke(OperationalSiteForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', OperationalSite::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                OperationalSiteForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
