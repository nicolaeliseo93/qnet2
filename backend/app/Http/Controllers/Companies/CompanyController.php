<?php

namespace App\Http\Controllers\Companies;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Companies\StoreCompanyRequest;
use App\Http\Requests\Companies\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `companies` resource (spec 0010), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (CompanyPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned company.
 *
 * @see CompanyService
 */
class CompanyController extends BaseApiController
{
    use AuthorizesRequests;

    /**
     * Relations CompanyResource's `address` block reads (geo names): loaded
     * explicitly on show() so it never lazy-loads (CompanyService already
     * loads them on create/update).
     *
     * @var array<int, string>
     */
    private const array ADDRESS_RELATIONS = ['addresses.country', 'addresses.state', 'addresses.province', 'addresses.city'];

    public function __construct(
        private readonly CompanyService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/companies/{company} — single company (view row-action).
     */
    public function show(Request $request, Company $company): JsonResponse
    {
        try {
            $this->authorize('view', $company);

            $company->load(self::ADDRESS_RELATIONS);

            return $this->okWithPermissions(new CompanyResource($company), $this->buildPermissions($request->user(), $company));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['company' => $company->id]);
        }
    }

    /**
     * POST /api/companies — create a new company.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Company::class);

            $company = $this->service->create($request->user(), $request->toData());

            return $this->okWithPermissions(
                new CompanyResource($company),
                $this->buildPermissions($request->user(), $company),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/companies/{company} — update an existing company (edit row-action).
     */
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        try {
            $this->authorize('update', $company);

            $company = $this->service->update($request->user(), $company, $request->toData());

            return $this->okWithPermissions(new CompanyResource($company), $this->buildPermissions($request->user(), $company));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['company' => $company->id]);
        }
    }

    /**
     * DELETE /api/companies/{company} — delete a company (delete row-action).
     */
    public function destroy(Company $company): JsonResponse
    {
        try {
            $this->authorize('delete', $company);

            $this->service->delete($company);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['company' => $company->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Company $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('companies'), $actor, $model);
    }
}
