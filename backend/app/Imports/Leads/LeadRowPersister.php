<?php

namespace App\Imports\Leads;

use App\DataObjects\Leads\CreateLeadData;
use App\DataObjects\Leads\UpdateLeadData;
use App\DataObjects\Referents\CreateReferentData;
use App\DataObjects\Referents\UpdateReferentData;
use App\Enums\ReferentContactScopeEnum;
use App\Models\Lead;
use App\Models\Referent;
use App\Models\User;
use App\Services\LeadService;
use App\Services\ReferentService;
use RuntimeException;

/**
 * The write side of `LeadsImportDefinition::persistRow()` (spec 0033
 * AC-011/012): resolves/creates the Referent per dedup strategy — via
 * ReferentService + LeadProfileBuilder, never duplicated anagraphic logic —
 * then creates/updates the Lead in the configured campaign via LeadService.
 * Extracted to stay under the 300-line soft limit (engineering.md §6).
 */
final class LeadRowPersister
{
    public function __construct(
        private readonly ReferentService $referentService,
        private readonly LeadService $leadService,
        private readonly LeadProfileBuilder $profileBuilder,
        private readonly LeadImportFieldCatalog $catalog,
    ) {}

    /**
     * @param  array<string, mixed>  $globalConfig
     * @param  array<string, mixed>  $mapped  field id => resolved value (after recognizers)
     * @param  array<string, mixed>  $extraValues
     */
    public function persist(
        User $actor,
        array $globalConfig,
        array $mapped,
        array $extraValues,
        bool $shouldUpdateReferent,
        ?int $duplicateReferentId,
    ): void {
        $referent = $shouldUpdateReferent && $duplicateReferentId !== null
            ? $this->updateReferent($actor, $duplicateReferentId, $mapped)
            : $this->createReferent($actor, $mapped);

        $this->attachLead($referent, $globalConfig, $mapped, $extraValues, $shouldUpdateReferent && $duplicateReferentId !== null);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function createReferent(User $actor, array $mapped): Referent
    {
        return $this->referentService->create(
            $actor,
            new CreateReferentData(referentTypeId: null, contactScope: ReferentContactScopeEnum::External->value, notes: null),
            $this->profileBuilder->build($mapped),
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function updateReferent(User $actor, int $referentId, array $mapped): Referent
    {
        $referent = Referent::query()
            ->with(['personalData.contacts', 'personalData.addresses'])
            ->findOrFail($referentId);

        return $this->referentService->update(
            $actor,
            $referent,
            new UpdateReferentData,
            $this->profileBuilder->buildForUpdate($referent, $mapped),
        );
    }

    /**
     * @param  array<string, mixed>  $globalConfig
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $extraValues
     */
    private function attachLead(Referent $referent, array $globalConfig, array $mapped, array $extraValues, bool $shouldUpdate): void
    {
        $campaignId = $this->id($globalConfig, 'campaign_id');

        if ($campaignId === null) {
            throw new RuntimeException('LeadsImportDefinition::persistRow requires a campaign_id in the global configuration.');
        }

        $leadStatusId = $this->id($globalConfig, 'lead_status_id') ?? $this->catalog->defaultLeadStatusId();

        if ($leadStatusId === null) {
            throw new RuntimeException('LeadsImportDefinition::persistRow requires a lead_status_id in the global configuration.');
        }

        $sourceId = $this->id($globalConfig, 'source_id');
        $operationalSiteId = $this->id($globalConfig, 'operational_site_id');
        $operatorId = $this->id($globalConfig, 'operator_id');
        $notes = $this->value($mapped, 'notes');
        $extraFields = $extraValues === [] ? null : $extraValues;

        $existingLead = $shouldUpdate
            ? Lead::query()->where('referent_id', $referent->id)->where('campaign_id', $campaignId)->first()
            : null;

        if ($existingLead !== null) {
            $this->leadService->update($existingLead, new UpdateLeadData(
                sourceId: $sourceId,
                sourceIdSubmitted: true,
                operationalSiteId: $operationalSiteId,
                operationalSiteIdSubmitted: true,
                operatorId: $operatorId,
                operatorIdSubmitted: true,
                leadStatusId: $leadStatusId,
                leadStatusIdSubmitted: true,
                notes: $notes,
                notesSubmitted: true,
                extraFields: $extraFields,
                extraFieldsSubmitted: true,
            ));

            return;
        }

        $this->leadService->create(new CreateLeadData(
            referentId: $referent->id,
            campaignId: $campaignId,
            operationalSiteId: $operationalSiteId,
            sourceId: $sourceId,
            operatorId: $operatorId,
            leadStatusId: $leadStatusId,
            notes: $notes,
            extraFields: $extraFields,
        ));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function value(array $values, string $field): ?string
    {
        $value = trim((string) ($values[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function id(array $values, string $field): ?int
    {
        $value = $values[$field] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }
}
