<?php

namespace App\Http\Controllers\VatRates;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\VatRates\VatRateForSelectRequest;
use App\Http\Resources\VatRateForSelectResource;
use App\Models\VatRate;
use App\Services\VatRateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/vat-rates/for-select — minimal, searchable, paginated VAT rate
 * list feeding entity-backed selects (ADR 0011 the for-select standard),
 * mirroring SourceForSelectController.
 *
 * Thin invokable controller: validation (VatRateForSelectRequest),
 * server-side authorization (vat-rates.viewAny via VatRatePolicy), Service
 * call, paginated response.
 *
 * @see VatRateService::forSelect
 */
class VatRateForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly VatRateService $service) {}

    public function __invoke(VatRateForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', VatRate::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                VatRateForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
