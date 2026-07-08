<?php

namespace App\Http\Controllers\EaSectors;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EaSectors\EaSectorForSelectRequest;
use App\Http\Resources\EaSectorForSelectResource;
use App\Models\EaSector;
use App\Services\EaSectorService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/ea-sectors/for-select — minimal, searchable, paginated EA-sector
 * list feeding entity-backed selects (spec 0020, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController. First producer: the
 * Registries form's "Settore EA / Competenze" multiselect (ea_sectors
 * previously only exposed the `tree` read view).
 *
 * Thin invokable controller: validation (EaSectorForSelectRequest),
 * server-side authorization (ea-sectors.viewAny via EaSectorPolicy), Service
 * call, paginated response.
 *
 * @see EaSectorService::forSelect
 */
class EaSectorForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly EaSectorService $service) {}

    public function __invoke(EaSectorForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', EaSector::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                EaSectorForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
