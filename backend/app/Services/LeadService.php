<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Leads\CreateLeadData;
use App\DataObjects\Leads\UpdateLeadData;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Services\Statuses\SystemStatusGuard;

/**
 * Business logic for the `leads` resource (spec 0024): plain create/update/
 * delete. No sequential code (D-3, unlike Project/Campaign) and no BR-3-style
 * cross-entity guard on write — the ONLY business rule (BR-2/D-4, blocking
 * cancellation of a referenced entity) lives in the 5 REFERENCED modules'
 * Services, not here (see CampaignService/ReferentService/
 * OperationalSiteService/SourceService/UserService).
 */
class LeadService
{
    public function __construct(private readonly SystemStatusGuard $systemStatusGuard) {}

    /**
     * Relations eager-loaded for the detail read tree (LeadResource), so a
     * single query never N+1s.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'referent',
        'campaign',
        'operationalSite.addresses.city',
        'source',
        'operator',
        'leadStatus',
    ];

    public function loadDetail(Lead $lead): Lead
    {
        return $lead->load(self::DETAIL_RELATIONS);
    }

    public function create(CreateLeadData $data): Lead
    {
        $attributes = $data->attributes();

        // spec 0039, D-3: an omitted/null FK falls back to the mandatory
        // system_key='new' status (resolved by system_key, never by name).
        $attributes['lead_status_id'] = $data->leadStatusId
            ?? $this->systemStatusGuard->resolveNewStatusId(LeadStatus::class);

        $lead = Lead::create($attributes);

        return $this->loadDetail($lead);
    }

    public function update(Lead $lead, UpdateLeadData $data): Lead
    {
        // Unconditional save: fire the model's saved event even when no
        // native attribute changed, so the HasCustomFields write pipeline
        // (spec 0021) persists a custom-fields-only edit.
        $lead->fill($data->submittedAttributes())->save();

        return $this->loadDetail($lead);
    }

    public function delete(Lead $lead): void
    {
        $lead->delete();
    }
}
