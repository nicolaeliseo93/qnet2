<?php

namespace App\Http\Controllers\ReferentTypes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ReferentTypes\ReferentTypeForSelectRequest;
use App\Http\Resources\ReferentTypeForSelectResource;
use App\Models\ReferentType;
use App\Services\ReferentTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/referent-types/for-select — minimal, searchable, paginated
 * referent-type list feeding the referent-form "Referent type" select (spec
 * 0016, ADR 0011 the for-select standard), mirroring
 * BusinessFunctionForSelectController.
 *
 * Thin invokable controller: validation (ReferentTypeForSelectRequest),
 * server-side authorization (referent-types.viewAny via ReferentTypePolicy),
 * Service call, paginated response.
 *
 * @see ReferentTypeService::forSelect
 */
class ReferentTypeForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ReferentTypeService $service) {}

    public function __invoke(ReferentTypeForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', ReferentType::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ReferentTypeForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
