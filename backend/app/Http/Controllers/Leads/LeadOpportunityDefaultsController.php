<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/leads/{lead}/opportunity-defaults (spec 0040, BR-1): the values an
 * Opportunity would inherit from $lead, feeding the "create opportunity from
 * lead" form's prefill. Single-action controller: the only logic is
 * authorization + a call into LeadOpportunityDefaultsResolver — the SAME
 * resolver StoreOpportunityRequest/UpdateOpportunityRequest/OpportunityService
 * use to enforce the BR-2 lock, so the prefill and the write-side enforcement
 * can never drift apart.
 *
 * Double-gated: `opportunities.create` (the actor may create the resource
 * this endpoint feeds) AND `leads.view` (the actor may see $lead itself).
 */
class LeadOpportunityDefaultsController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly LeadOpportunityDefaultsResolver $resolver) {}

    public function __invoke(Lead $lead): JsonResponse
    {
        try {
            $this->authorize('create', Opportunity::class);
            $this->authorize('view', $lead);

            $defaults = $this->resolver->resolve($lead);

            return $this->ok([
                'lead_id' => $lead->id,
                'existing_opportunity_id' => $defaults->existingOpportunityId,
                'values' => $defaults->values,
                'references' => $defaults->references,
                'locked_fields' => $defaults->lockedFields,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['lead' => $lead->id]);
        }
    }
}
