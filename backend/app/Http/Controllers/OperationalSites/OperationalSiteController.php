<?php

namespace App\Http\Controllers\OperationalSites;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\OperationalSites\StoreOperationalSiteRequest;
use App\Http\Requests\OperationalSites\UpdateOperationalSiteRequest;
use App\Http\Resources\OperationalSiteResource;
use App\Models\OperationalSite;
use App\Models\User;
use App\Services\OperationalSiteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `operational-sites` resource (spec 0011), backing
 * the backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (OperationalSitePolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned site.
 *
 * @see OperationalSiteService
 */
class OperationalSiteController extends BaseApiController
{
    use AuthorizesRequests;

    /**
     * Relations OperationalSiteResource reads (geo names): loaded explicitly
     * on show() so it never lazy-loads (OperationalSiteService already loads
     * them on create/update).
     *
     * @var array<int, string>
     */
    private const array ADDRESS_RELATIONS = ['addresses.country', 'addresses.state', 'addresses.province', 'addresses.city'];

    public function __construct(
        private readonly OperationalSiteService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/operational-sites/{operationalSite} — single site (view row-action).
     */
    public function show(Request $request, OperationalSite $operationalSite): JsonResponse
    {
        try {
            $this->authorize('view', $operationalSite);

            $operationalSite->load(self::ADDRESS_RELATIONS);

            return $this->okWithPermissions(
                new OperationalSiteResource($operationalSite),
                $this->buildPermissions($request->user(), $operationalSite),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['operationalSite' => $operationalSite->id]);
        }
    }

    /**
     * POST /api/operational-sites — create a new site.
     */
    public function store(StoreOperationalSiteRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', OperationalSite::class);

            $operationalSite = $this->service->create($request->user(), $request->toData());

            return $this->okWithPermissions(
                new OperationalSiteResource($operationalSite),
                $this->buildPermissions($request->user(), $operationalSite),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/operational-sites/{operationalSite} — update an existing site.
     */
    public function update(UpdateOperationalSiteRequest $request, OperationalSite $operationalSite): JsonResponse
    {
        try {
            $this->authorize('update', $operationalSite);

            $operationalSite = $this->service->update($request->user(), $operationalSite, $request->toData());

            return $this->okWithPermissions(
                new OperationalSiteResource($operationalSite),
                $this->buildPermissions($request->user(), $operationalSite),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['operationalSite' => $operationalSite->id]);
        }
    }

    /**
     * DELETE /api/operational-sites/{operationalSite} — delete a site.
     */
    public function destroy(OperationalSite $operationalSite): JsonResponse
    {
        try {
            $this->authorize('delete', $operationalSite);

            $this->service->delete($operationalSite);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['operationalSite' => $operationalSite->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?OperationalSite $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('operational-sites'), $actor, $model);
    }
}
