<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Products\ProductForSelectRequest;
use App\Http\Resources\ProductForSelectResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/products/for-select — minimal, searchable, paginated product list
 * feeding entity-backed selects (ADR 0011 the for-select standard),
 * mirroring ProductCategoryForSelectController. Feeds the "prodotti di
 * interesse" picker of the opportunity form and of the request-management
 * work panel, optionally scoped by `category_ids[]`.
 *
 * Thin invokable controller: validation (ProductForSelectRequest),
 * server-side authorization (products.viewAny via ProductPolicy), Service
 * call, paginated response.
 *
 * @see ProductService::forSelect
 */
class ProductForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ProductService $service) {}

    public function __invoke(ProductForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Product::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                ProductForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
