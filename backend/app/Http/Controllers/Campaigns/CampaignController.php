<?php

namespace App\Http\Controllers\Campaigns;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Campaigns\StoreCampaignRequest;
use App\Http\Requests\Campaigns\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\User;
use App\Services\CampaignService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `campaigns` resource (spec 0023), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (CampaignPolicy), Service call, response. No business logic, no queries —
 * BR-1/BR-2/BR-3 all live in CampaignService.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned campaign.
 *
 * @see CampaignService
 */
class CampaignController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CampaignService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/campaigns/{campaign} — single campaign (view row-action).
     */
    public function show(Request $request, Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('view', $campaign);

            return $this->okWithPermissions(
                new CampaignResource($this->service->loadDetail($campaign)),
                $this->buildPermissions($request->user(), $campaign),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['campaign' => $campaign->id]);
        }
    }

    /**
     * POST /api/campaigns — create a new campaign.
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Campaign::class);

            $campaign = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new CampaignResource($campaign),
                $this->buildPermissions($request->user(), $campaign),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/campaigns/{campaign} — update an existing campaign.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('update', $campaign);

            $campaign = $this->service->update($campaign, $request->toData());

            return $this->okWithPermissions(
                new CampaignResource($campaign),
                $this->buildPermissions($request->user(), $campaign),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['campaign' => $campaign->id]);
        }
    }

    /**
     * DELETE /api/campaigns/{campaign} — delete a campaign (no delete-guard,
     * unlike Projects/PipelineStatuses).
     */
    public function destroy(Campaign $campaign): JsonResponse
    {
        try {
            $this->authorize('delete', $campaign);

            $this->service->delete($campaign);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['campaign' => $campaign->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Campaign $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('campaigns'), $actor, $model);
    }
}
