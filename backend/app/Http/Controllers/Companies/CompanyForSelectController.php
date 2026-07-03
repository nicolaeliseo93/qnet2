<?php

namespace App\Http\Controllers\Companies;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Companies\CompanyForSelectRequest;
use App\Http\Resources\CompanyForSelectResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/companies/for-select — minimal, searchable, paginated company list
 * feeding the user-form "company" select (spec 0015, ADR 0011 the for-select
 * standard), mirroring UserForSelectController.
 *
 * Thin invokable controller: validation (CompanyForSelectRequest), server-side
 * authorization (companies.viewAny via CompanyPolicy), Service call, paginated
 * response. The query/search/hydration logic lives in CompanyService::forSelect,
 * not here.
 *
 * @see CompanyService::forSelect
 */
class CompanyForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CompanyService $service) {}

    public function __invoke(CompanyForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Company::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                CompanyForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
