<?php

namespace App\Http\Controllers\CompanySites;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CompanySites\SetDefaultCompanySiteRequest;
use App\Http\Requests\CompanySites\StoreCompanySiteRequest;
use App\Http\Requests\CompanySites\UpdateCompanySiteRequest;
use App\Http\Requests\CompanySites\UploadCompanySiteLogoRequest;
use App\Http\Resources\CompanySiteResource;
use App\Models\CompanySite;
use App\Models\User;
use App\Services\CompanySiteService;
use App\Services\LogoService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD + logo/set-default endpoints for the `company-sites` resource (spec
 * 0020), backing the backend-driven table row-actions (view/edit/delete) plus
 * create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (CompanySitePolicy), Service call, response. No business logic, no queries.
 *
 * Every action also attaches the `permissions` metadata block (spec 0004) via
 * ResourcePermissionsBuilder, contextual to the returned site.
 *
 * @see CompanySiteService
 */
class CompanySiteController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CompanySiteService $service,
        private readonly LogoService $logos,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/company-sites/{companySite} — single site (view row-action).
     */
    public function show(Request $request, CompanySite $companySite): JsonResponse
    {
        try {
            $this->authorize('view', $companySite);

            $companySite = $this->service->loadTree($companySite);

            return $this->okWithPermissions(new CompanySiteResource($companySite), $this->buildPermissions($request->user(), $companySite));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companySite' => $companySite->id]);
        }
    }

    /**
     * POST /api/company-sites — create a new site.
     */
    public function store(StoreCompanySiteRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', CompanySite::class);

            $companySite = $this->service->create($request->user(), $request->toData(), $request->toProfile());

            return $this->okWithPermissions(
                new CompanySiteResource($companySite),
                $this->buildPermissions($request->user(), $companySite),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/company-sites/{companySite} — update a site (edit row-action).
     */
    public function update(UpdateCompanySiteRequest $request, CompanySite $companySite): JsonResponse
    {
        try {
            $this->authorize('update', $companySite);

            $companySite = $this->service->update($request->user(), $companySite, $request->toData(), $request->toProfile());

            return $this->okWithPermissions(new CompanySiteResource($companySite), $this->buildPermissions($request->user(), $companySite));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companySite' => $companySite->id]);
        }
    }

    /**
     * DELETE /api/company-sites/{companySite} — delete a site (delete row-action).
     */
    public function destroy(CompanySite $companySite): JsonResponse
    {
        try {
            $this->authorize('delete', $companySite);

            $this->service->delete($companySite);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companySite' => $companySite->id]);
        }
    }

    /**
     * POST /api/company-sites/{companySite}/set-default — exclusively promote
     * this site to the default one. Gated by `update`, same as editing the site.
     */
    public function setDefault(SetDefaultCompanySiteRequest $request, CompanySite $companySite): JsonResponse
    {
        try {
            $this->authorize('update', $companySite);

            $this->service->setDefault($companySite);
            $companySite = $this->service->loadTree($companySite);

            return $this->okWithPermissions(new CompanySiteResource($companySite), $this->buildPermissions($request->user(), $companySite));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companySite' => $companySite->id]);
        }
    }

    /**
     * POST /api/company-sites/{companySite}/logo — upload/replace the site's
     * logo. Gated by `update`, same as editing the site.
     */
    public function uploadLogo(UploadCompanySiteLogoRequest $request, CompanySite $companySite): JsonResponse
    {
        try {
            $this->authorize('update', $companySite);

            $this->logos->set($companySite, $request->logoFile());
            $companySite = $this->service->loadTree($companySite);

            return $this->okWithPermissions(new CompanySiteResource($companySite), $this->buildPermissions($request->user(), $companySite));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companySite' => $companySite->id]);
        }
    }

    /**
     * DELETE /api/company-sites/{companySite}/logo — remove the site's logo.
     */
    public function deleteLogo(Request $request, CompanySite $companySite): JsonResponse
    {
        try {
            $this->authorize('update', $companySite);

            $this->logos->remove($companySite);
            $companySite = $this->service->loadTree($companySite);

            return $this->okWithPermissions(new CompanySiteResource($companySite), $this->buildPermissions($request->user(), $companySite));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['companySite' => $companySite->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?CompanySite $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('company-sites'), $actor, $model);
    }
}
