<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Auth\UploadAvatarRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AvatarService;
use App\Services\UserService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the Users resource, backing the backend-driven table
 * row-actions (view / edit / delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (UserPolicy), Service call, response. No business logic, no queries.
 *
 * Authorization is re-enforced server-side on every action because these routes
 * are hit by frontend row-actions, which are NOT the source of truth.
 *
 * @see UserService
 */
class UserController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly UserService $service) {}

    /**
     * GET /api/users/{user} — single user (view row-action).
     */
    public function show(User $user): JsonResponse
    {
        try {
            $this->authorize('view', $user);

            return $this->ok(new UserResource($user));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * POST /api/users — create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', User::class);

            $user = $this->service->create($request->user(), $request->toData(), $request->toProfile());

            return $this->created(new UserResource($user));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/users/{user} — update an existing user (edit row-action).
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $this->authorize('update', $user);

            $user = $this->service->update($request->user(), $user, $request->toData(), $request->toProfile());

            return $this->ok(new UserResource($user));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * DELETE /api/users/{user} — delete a user (delete row-action).
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            $this->authorize('delete', $user);

            $this->service->delete($user);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * POST /api/users/{user}/avatar — upload/replace a user's avatar (admin).
     *
     * Gated by the same `users.update` ability as editing the user: managing a
     * user's avatar is part of managing the user.
     */
    public function uploadAvatar(UploadAvatarRequest $request, User $user, AvatarService $avatars): JsonResponse
    {
        try {
            $this->authorize('update', $user);

            $avatars->set($user, $request->avatarFile());

            return $this->ok(new UserResource($user->refresh()), __('auth.avatar_updated'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * DELETE /api/users/{user}/avatar — remove a user's avatar (admin).
     */
    public function deleteAvatar(User $user, AvatarService $avatars): JsonResponse
    {
        try {
            $this->authorize('update', $user);

            $avatars->remove($user);

            return $this->ok(new UserResource($user->refresh()), __('auth.avatar_removed'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }
}
