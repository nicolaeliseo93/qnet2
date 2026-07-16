<?php

namespace App\Http\Controllers\CompanySites;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CompanySites\CompanySiteForSelectRequest;
use App\Http\Resources\CompanySiteForSelectResource;
use App\Models\CompanySite;
use App\Services\CompanySiteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/company-sites/for-select — minimal, searchable, paginated
 * company-site list feeding the Opportunity form's "company_site" select
 * (spec 0040, ADR 0011 the for-select standard), mirroring
 * CompanyForSelectController.
 *
 * Thin invokable controller: validation (CompanySiteForSelectRequest),
 * server-side authorization (company-sites.viewAny via CompanySitePolicy),
 * Service call, paginated response. The query/search/hydration/scope logic
 * lives in CompanySiteService::forSelect, not here.
 *
 * @see CompanySiteService::forSelect
 */
class CompanySiteForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CompanySiteService $service) {}

    public function __invoke(CompanySiteForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', CompanySite::class);

            $result = $this->service->forSelect($request->toData(), $request->companyId());

            return $this->paginatedResponse(
                CompanySiteForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
