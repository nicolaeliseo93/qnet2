<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\UpdateRequestRequest;
use App\Http\Resources\RequestManagementResource;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\RequestManagement\RequestManagementScope;
use App\Services\RequestManagement\RequestManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Dedicated show/update endpoints for the "Gestione Richieste" work panel
 * (spec 0049): the record IS an Opportunity (D-1), but access runs through
 * its OWN `request-management.*` permissions and D-3 scoping guard, never
 * `opportunities.*`/PATCH /api/opportunities/{id}.
 *
 * Thin controller: permission gate + scope guard, FormRequest validation,
 * Service call, Resource output. Both actions attach the same `permissions`
 * metadata block (spec 0004) as OpportunityController.
 *
 * @see RequestManagementService
 * @see RequestManagementScope
 */
class RequestManagementController extends BaseApiController
{
    public function __construct(
        private readonly RequestManagementService $service,
        private readonly RequestManagementScope $scope,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/request-management/{opportunity} — the work panel.
     */
    public function show(Request $request, Opportunity $opportunity): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.view'), 403);
            $this->scope->assertInScope($user, $opportunity);

            return $this->okWithPermissions(
                new RequestManagementResource($this->service->loadWorkPanel($opportunity)),
                $this->buildPermissions($user, $opportunity),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunity' => $opportunity->id]);
        }
    }

    /**
     * PUT/PATCH /api/request-management/{opportunity} — advance the working
     * state and/or persist dynamic field values (sparse, D-4/D-5).
     */
    public function update(UpdateRequestRequest $request, Opportunity $opportunity): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.update'), 403);
            $this->scope->assertInScope($user, $opportunity);

            $panel = $this->service->updateWork(
                $opportunity,
                $user,
                [
                    ...$request->safe()->only(['opportunity_workflow_status_id', 'attribute_values', 'next_callback_at']),
                    // Typed DTOs (ContactInput/AddressInput), not raw arrays:
                    // the client anagraphic block never reaches the service as
                    // request input.
                    ...$request->clientProfilePayload(),
                ],
            );

            return $this->okWithPermissions(
                new RequestManagementResource($panel),
                $this->buildPermissions($user, $opportunity),
                'Updated',
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunity' => $opportunity->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Opportunity $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('request-management'), $actor, $model);
    }
}
