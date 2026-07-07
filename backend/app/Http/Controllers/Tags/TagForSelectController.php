<?php

namespace App\Http\Controllers\Tags;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tags\TagForSelectRequest;
use App\Http\Resources\TagForSelectResource;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/tags/for-select — minimal, searchable, paginated tag list
 * feeding entity-backed selects (spec 0019, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation (TagForSelectRequest),
 * server-side authorization (tags.viewAny via TagPolicy), Service
 * call, paginated response.
 *
 * @see TagService::forSelect
 */
class TagForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly TagService $service) {}

    public function __invoke(TagForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Tag::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                TagForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
