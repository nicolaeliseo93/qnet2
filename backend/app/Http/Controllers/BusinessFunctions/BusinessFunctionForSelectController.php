<?php

namespace App\Http\Controllers\BusinessFunctions;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\BusinessFunctions\BusinessFunctionForSelectRequest;
use App\Http\Resources\BusinessFunctionForSelectResource;
use App\Models\BusinessFunction;
use App\Services\BusinessFunctionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/business-functions/for-select — minimal, searchable, paginated
 * business-function list feeding the user-form "function" select (spec 0015,
 * ADR 0011 the for-select standard), mirroring UserForSelectController.
 *
 * Thin invokable controller: validation (BusinessFunctionForSelectRequest),
 * server-side authorization (business-functions.viewAny via
 * BusinessFunctionPolicy), Service call, paginated response. The query/
 * search/hydration logic lives in BusinessFunctionService::forSelect, not here.
 *
 * @see BusinessFunctionService::forSelect
 */
class BusinessFunctionForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly BusinessFunctionService $service) {}

    public function __invoke(BusinessFunctionForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', BusinessFunction::class);

            $result = $this->service->forSelect($request->toData(), $request->excludeDescendantsOf());

            return $this->paginatedResponse(
                BusinessFunctionForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
