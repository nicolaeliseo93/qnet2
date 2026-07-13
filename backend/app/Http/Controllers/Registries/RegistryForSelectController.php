<?php

namespace App\Http\Controllers\Registries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Registries\RegistryForSelectRequest;
use App\Http\Resources\RegistryForSelectResource;
use App\Models\Registry;
use App\Services\RegistryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/registries/for-select — minimal, searchable, paginated registry
 * list feeding entity-backed selects (spec 0023, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation (RegistryForSelectRequest),
 * server-side authorization (registries.viewAny via RegistryPolicy), Service
 * call, paginated response.
 *
 * @see RegistryService::forSelect
 */
class RegistryForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly RegistryService $service) {}

    public function __invoke(RegistryForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Registry::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                RegistryForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
