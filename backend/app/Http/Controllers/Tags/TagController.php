<?php

namespace App\Http\Controllers\Tags;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tags\StoreTagRequest;
use App\Http\Requests\Tags\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `tags` resource (spec 0019), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TagPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see TagService
 */
class TagController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TagService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/tags/{tag} — single tag (view row-action).
     */
    public function show(Request $request, Tag $tag): JsonResponse
    {
        try {
            $this->authorize('view', $tag);

            return $this->okWithPermissions(
                new TagResource($tag),
                $this->buildPermissions($request->user(), $tag),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['tag' => $tag->id]);
        }
    }

    /**
     * POST /api/tags — create a new tag.
     */
    public function store(StoreTagRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Tag::class);

            $tag = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new TagResource($tag),
                $this->buildPermissions($request->user(), $tag),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/tags/{tag} — update an existing tag.
     */
    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        try {
            $this->authorize('update', $tag);

            $tag = $this->service->update($tag, $request->toData());

            return $this->okWithPermissions(
                new TagResource($tag),
                $this->buildPermissions($request->user(), $tag),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['tag' => $tag->id]);
        }
    }

    /**
     * DELETE /api/tags/{tag} — delete a tag.
     */
    public function destroy(Tag $tag): JsonResponse
    {
        try {
            $this->authorize('delete', $tag);

            $this->service->delete($tag);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['tag' => $tag->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Tag $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('tags'), $actor, $model);
    }
}
