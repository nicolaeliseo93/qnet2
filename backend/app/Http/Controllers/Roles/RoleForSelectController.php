<?php

namespace App\Http\Controllers\Roles;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Roles\RoleForSelectRequest;
use App\Http\Resources\RoleForSelectResource;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/roles/for-select — minimal, searchable, paginated role list feeding
 * the User-form role multi-select (ADR 0011, the for-select standard). The role
 * counterpart of UserForSelectController.
 *
 * Thin invokable controller: validation (RoleForSelectRequest), server-side
 * authorization (roles.viewAny via RolePolicy), Service call, paginated response.
 * The options are scoped to the ACTOR's assignable roles (a non super-admin never
 * sees `super-admin`) inside RoleService::forSelect — same source of truth as the
 * user-form role rule and the users table `roles` filter.
 *
 * @see RoleService::forSelect
 */
class RoleForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly RoleService $service) {}

    public function __invoke(RoleForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Role::class);

            /** @var User $actor */
            $actor = $request->user();

            $result = $this->service->forSelect($request->toData(), $actor);

            return $this->paginatedResponse(
                RoleForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
