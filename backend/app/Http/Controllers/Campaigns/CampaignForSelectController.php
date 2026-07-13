<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Campaigns\CampaignForSelectRequest;
use App\Http\Resources\CampaignForSelectResource;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/campaigns/for-select — minimal, searchable, paginated campaign
 * list feeding entity-backed selects (spec 0024, ADR 0011), mirroring
 * ProjectForSelectController. Feeds the Lead form's campaign field.
 *
 * Thin invokable controller: validation (CampaignForSelectRequest),
 * server-side authorization (campaigns.viewAny via CampaignPolicy), Service
 * call, paginated response.
 *
 * @see CampaignService::forSelect
 */
class CampaignForSelectController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CampaignService $service) {}

    public function __invoke(CampaignForSelectRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Campaign::class);

            $result = $this->service->forSelect($request->toData());

            return $this->paginatedResponse(
                CampaignForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
