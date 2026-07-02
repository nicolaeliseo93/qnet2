<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Users\UserForSelectRequest;
use App\Http\Resources\UserForSelectResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/users/for-select — minimal, searchable, paginated user list feeding
 * the Role-form user multi-select (ADR 0011, the for-select standard).
 *
 * Thin invokable controller: validation (UserForSelectRequest), server-side
 * authorization (users.viewAny via UserPolicy), Service call, paginated response.
 * The query/search/hydration logic lives in UserService::forSelect, not here.
 *
 * @see UserService::forSelect
 */
class UserForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly UserService $service) {}

    public function __invoke(UserForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', User::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                UserForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
