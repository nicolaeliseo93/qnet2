<?php

namespace App\Http\Controllers\Sectors;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Sectors\SectorForSelectRequest;
use App\Http\Resources\SectorForSelectResource;
use App\Models\Sector;
use App\Services\SectorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/sectors/for-select — minimal, searchable, paginated sector
 * list feeding entity-backed selects (spec 0020, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController. First producer: the
 * Registries form's "Settore EA / Competenze" multiselect (sectors
 * previously only exposed the `tree` read view).
 *
 * Thin invokable controller: validation (SectorForSelectRequest),
 * server-side authorization (sectors.viewAny via SectorPolicy), Service
 * call, paginated response.
 *
 * @see SectorService::forSelect
 */
class SectorForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly SectorService $service) {}

    public function __invoke(SectorForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Sector::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                SectorForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
