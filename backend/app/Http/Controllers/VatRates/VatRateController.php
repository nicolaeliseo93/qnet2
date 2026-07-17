<?php

namespace App\Http\Controllers\VatRates;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\VatRates\StoreVatRateRequest;
use App\Http\Requests\VatRates\UpdateVatRateRequest;
use App\Http\Resources\VatRateResource;
use App\Models\User;
use App\Models\VatRate;
use App\Services\VatRateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `vat-rates` resource, backing the backend-driven
 * table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (VatRatePolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see VatRateService
 */
class VatRateController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly VatRateService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/vat-rates/{vatRate} — single VAT rate (view row-action).
     */
    public function show(Request $request, VatRate $vatRate): JsonResponse
    {
        try {
            $this->authorize('view', $vatRate);

            return $this->okWithPermissions(
                new VatRateResource($vatRate),
                $this->buildPermissions($request->user(), $vatRate),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['vatRate' => $vatRate->id]);
        }
    }

    /**
     * POST /api/vat-rates — create a new VAT rate.
     */
    public function store(StoreVatRateRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', VatRate::class);

            $vatRate = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new VatRateResource($vatRate),
                $this->buildPermissions($request->user(), $vatRate),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/vat-rates/{vatRate} — update an existing VAT rate.
     */
    public function update(UpdateVatRateRequest $request, VatRate $vatRate): JsonResponse
    {
        try {
            $this->authorize('update', $vatRate);

            $vatRate = $this->service->update($vatRate, $request->toData());

            return $this->okWithPermissions(
                new VatRateResource($vatRate),
                $this->buildPermissions($request->user(), $vatRate),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['vatRate' => $vatRate->id]);
        }
    }

    /**
     * DELETE /api/vat-rates/{vatRate} — delete a VAT rate.
     */
    public function destroy(VatRate $vatRate): JsonResponse
    {
        try {
            $this->authorize('delete', $vatRate);

            $this->service->delete($vatRate);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['vatRate' => $vatRate->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?VatRate $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('vat-rates'), $actor, $model);
    }
}
