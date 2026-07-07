<?php

namespace App\Http\Controllers\Sources;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Sources\SourceForSelectRequest;
use App\Http\Resources\SourceForSelectResource;
use App\Models\Source;
use App\Services\SourceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/sources/for-select — minimal, searchable, paginated source list
 * feeding entity-backed selects (spec 0018, ADR 0011 the for-select
 * standard), mirroring ReferentTypeForSelectController.
 *
 * Thin invokable controller: validation (SourceForSelectRequest),
 * server-side authorization (sources.viewAny via SourcePolicy), Service
 * call, paginated response.
 *
 * @see SourceService::forSelect
 */
class SourceForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly SourceService $service) {}

    public function __invoke(SourceForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Source::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                SourceForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
