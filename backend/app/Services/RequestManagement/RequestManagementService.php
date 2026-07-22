<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Notes\CreateNoteData;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\ApplicableAttributesResolver;
use App\RequestManagement\AttributeValueNormalizer;
use App\RequestManagement\AttributeValueValidator;
use App\Services\Notes\NoteService;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the request-management work panel (spec 0049): the
 * record IS an Opportunity (D-1), read/written through this dedicated
 * service rather than OpportunityService/OpportunityController — the
 * operative endpoints have their OWN authorization (`request-management.*`)
 * and their own write rules (D-4/D-5).
 *
 * `loadWorkPanel()`/`updateWork()` both return the SAME shape —
 * {opportunity, applicable_attributes, workflow_statuses} — consumed directly
 * by RequestManagementResource, so show/update render identically (data
 * contract: "Response identica alla GET").
 *
 * Activity logging: `opportunity_workflow_status_id`, `attribute_values` and
 * (spec 0052 D-2) `next_callback_at` are ALL deliberately excluded from
 * `Opportunity::$fillable` (mass-assignment guard), and
 * `LogsModelActivity::getActivitylogOptions()` calls
 * `logFillable()` — Spatie's dirty-diff only ever inspects the model's
 * fillable attributes. A change to either column therefore never reaches the
 * automatic model-event log. `updateWork()` compensates with an EXPLICIT
 * `activity()` call carrying the same `attributes`/`old` property shape the
 * automatic log would have produced, so GET /api/activity-log/request-
 * management/{id} (reading the Opportunity's own activity rows, D-7) still
 * sees the operative change (AC-043).
 *
 * Spec 0054, D-5: this is the ONE choke point for the working-status
 * advance, reached BOTH by the work panel (UpdateRequestRequest) and by the
 * inline-edit engine (RequestManagementTableDefinition::updateCell()) — a
 * status requiring a note (`requires_note`) enforces it HERE, so the two
 * write channels can never diverge on the rule.
 */
final class RequestManagementService
{
    /**
     * The `notes.notable_types` slug this module registers itself under
     * (config/notes.php) — the same entity a status-change note attaches to
     * as the collaborative-notes dialog would (spec 0052/0054 D-5).
     */
    private const string NOTE_ENTITY_TYPE = 'request-management';

    /**
     * Relations the work panel needs, eager-loaded in one shot (N+1-free):
     * contacts hang off each side's PersonalData card (HasPersonalData ->
     * HasContacts), never directly off Registry/Referent.
     *
     * @var array<int, string>
     */
    private const array WORK_PANEL_RELATIONS = [
        'registry.personalData.contacts',
        // The client's address is edited inline in the panel's "anagrafica"
        // section, with its geo names hydrated for the cascading selects.
        'registry.personalData.addresses.city',
        'registry.personalData.addresses.province',
        'registry.personalData.addresses.state',
        'registry.personalData.addresses.country',
        'referent.personalData.contacts',
        'commercial',
        'opportunityStatus',
        'workflowStatus',
        'productLines.businessFunction',
        'productLines.productCategory',
        'managers',
    ];

    public function __construct(
        private readonly ApplicableAttributesResolver $attributesResolver,
        private readonly AttributeValueValidator $attributeValueValidator,
        private readonly AttributeValueNormalizer $attributeValueNormalizer,
        private readonly OpportunityWorkflowResolver $workflowResolver,
        private readonly RequestClientProfileWriter $clientProfileWriter,
        private readonly NoteService $noteService,
    ) {}

    /**
     * @return array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, workflow_statuses: Collection<int, OpportunityWorkflowStatus>}
     */
    public function loadWorkPanel(Opportunity $opportunity): array
    {
        $opportunity->loadMissing(self::WORK_PANEL_RELATIONS);

        return [
            'opportunity' => $opportunity,
            'applicable_attributes' => $this->attributesResolver->resolve($opportunity),
            'workflow_statuses' => $this->resolveWorkflowStatuses($opportunity),
        ];
    }

    /**
     * Applies the sparse PATCH payload (spec 0049 data_contract: only the
     * submitted keys change) and returns the SAME work-panel shape as
     * loadWorkPanel(), post-save.
     *
     * @param  array{opportunity_workflow_status_id?: int|null, note?: string|null, attribute_values?: array<string, mixed>, next_callback_at?: string|null, client_identity?: CreatePersonalData, client_contacts?: array<int, ContactInput>, client_address?: AddressInput}  $data
     * @return array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, workflow_statuses: Collection<int, OpportunityWorkflowStatus>}
     */
    public function updateWork(Opportunity $opportunity, User $actor, array $data): array
    {
        return DB::transaction(function () use ($opportunity, $actor, $data): array {
            $changed = [];
            $old = [];

            // Step 1: working-state advance — set-membership (AC-011) and the
            // mandatory-note rule (spec 0054, D-5) both enforced HERE, the one
            // choke point both write channels pass through.
            if (array_key_exists('opportunity_workflow_status_id', $data) && $data['opportunity_workflow_status_id'] !== null) {
                $this->applyWorkflowStatus($opportunity, (int) $data['opportunity_workflow_status_id'], $actor, $data['note'] ?? null, $changed, $old);
            }

            // Step 2: dynamic field values — validate against the CURRENT
            // applicable set (AttributeValueValidator, keyed
            // attribute_values.<code> on failure), then merge into the
            // existing map (sparse: unset codes keep their persisted value).
            if (array_key_exists('attribute_values', $data)) {
                $this->applyAttributeValues($opportunity, (array) $data['attribute_values'], $changed, $old);
            }

            // Step 3: next planned callback (spec 0052 D-1/D-4) — sparse:
            // key absent leaves the persisted value untouched, `null` clears
            // it. A real value change also zeroes the reminder marker so a
            // rescheduled date is not skipped by the future reminder job.
            if (array_key_exists('next_callback_at', $data)) {
                $this->applyNextCallbackAt($opportunity, $data['next_callback_at'], $changed, $old);
            }

            $opportunity->save();

            // Step 4: client anagraphic block (spec 0049 amendment) — identity,
            // contacts and address land on the Registry's PersonalData card,
            // not on the opportunity, so they are written outside the model save.
            $this->applyClientProfile($opportunity, $data);

            // Step 5: explicit activity entry (see class docblock).
            $this->logOperationalChange($opportunity, $actor, $changed, $old);

            return $this->loadWorkPanel($opportunity);
        });
    }

    /**
     * Delegates the submitted client anagraphic keys to the dedicated writer,
     * then drops the loaded `registry` chain so the panel rebuilt right after
     * re-reads the just-written card/contacts/addresses instead of the stale
     * relation loaded before the save.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyClientProfile(Opportunity $opportunity, array $data): void
    {
        $submitted = array_intersect_key($data, array_flip(['client_identity', 'client_contacts', 'client_address']));

        if ($submitted === []) {
            return;
        }

        $this->clientProfileWriter->write(
            $opportunity,
            $data['client_identity'] ?? null,
            $data['client_contacts'] ?? null,
            $data['client_address'] ?? null,
        );

        $opportunity->unsetRelation('registry');
    }

    /**
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException the target status is outside the resolved workflow (AC-011), or it `requires_note` and none was given (spec 0054, D-5)
     * @throws AuthorizationException the actor cannot create the note (D-5)
     */
    private function applyWorkflowStatus(Opportunity $opportunity, int $newStatusId, User $actor, ?string $note, array &$changed, array &$old): void
    {
        $currentStatusId = $opportunity->opportunity_workflow_status_id;

        if ($currentStatusId === $newStatusId) {
            return; // resending the current status is not an advance (mirrors D-4's callback-instant comparison): no note requirement either.
        }

        // AC-011: the same resolved-workflow membership ValidatesWorkflowStatus
        // already enforces for the panel channel — re-checked here so the
        // inline-edit channel (which never goes through that FormRequest)
        // gets the identical guarantee, never a second/different rule.
        $allowedIds = $this->workflowResolver->statusesFor($this->workflowResolver->resolve($opportunity))->pluck('id');

        if (! $allowedIds->contains($newStatusId)) {
            throw ValidationException::withMessages([
                'opportunity_workflow_status_id' => ["The selected working status does not belong to the opportunity's resolved workflow."],
            ]);
        }

        // Spec 0054, D-5: a genuine advance to a `requires_note` status
        // demands one; the note itself is created via the SAME collaborative-
        // notes mechanism the dialog uses (spec 0052), inside THIS
        // transaction, so a note failure rolls back the status change too
        // (AC-010).
        $targetStatus = OpportunityWorkflowStatus::query()->findOrFail($newStatusId);

        if ($targetStatus->requires_note) {
            $this->createStatusChangeNote($opportunity, $actor, $note);
        }

        $old['opportunity_workflow_status_id'] = $currentStatusId;
        $opportunity->opportunity_workflow_status_id = $newStatusId;
        $changed['opportunity_workflow_status_id'] = $newStatusId;
    }

    /**
     * @throws ValidationException $note is missing/blank (AC-009)
     * @throws AuthorizationException the actor lacks `notes.create` (mirrors NoteController::store())
     */
    private function createStatusChangeNote(Opportunity $opportunity, User $actor, ?string $note): void
    {
        if ($note === null || trim($note) === '') {
            throw ValidationException::withMessages([
                'note' => ['A note is required when moving to this working status.'],
            ]);
        }

        if (! $actor->can('notes.create')) {
            throw new AuthorizationException;
        }

        $this->noteService->create($actor, new CreateNoteData(
            entityType: self::NOTE_ENTITY_TYPE,
            entityId: $opportunity->getKey(),
            body: $note,
            parentId: null,
            mentionIds: [],
        ));
    }

    /**
     * `next_callback_at` (spec 0052 D-1/D-2): NOT in Opportunity::$fillable,
     * assigned directly here (never mass-assigned). $value is whatever the
     * request submitted — a date string or null — and the 'datetime' cast
     * normalizes it as soon as it is set, so both sides of the comparison
     * below read back through the SAME cast (D-4 invariant: the reminder
     * marker is zeroed if and only if the resolved instant actually changes).
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function applyNextCallbackAt(Opportunity $opportunity, mixed $value, array &$changed, array &$old): void
    {
        $previous = $opportunity->next_callback_at;
        $opportunity->next_callback_at = $value;
        $current = $opportunity->next_callback_at;

        if ($this->callbackInstantKey($previous) === $this->callbackInstantKey($current)) {
            return;
        }

        $old['next_callback_at'] = $this->callbackInstantKey($previous);
        $changed['next_callback_at'] = $this->callbackInstantKey($current);
        $opportunity->next_callback_reminded_at = null;
    }

    /**
     * A comparable/loggable representation of a `next_callback_at` instant —
     * null-safe, so two nulls compare equal without a Carbon method call.
     */
    private function callbackInstantKey(?Carbon $value): ?string
    {
        return $value?->format('Y-m-d\TH:i');
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException
     */
    private function applyAttributeValues(Opportunity $opportunity, array $submitted, array &$changed, array &$old): void
    {
        $applicable = $this->attributesResolver->resolve($opportunity);
        $validated = $this->attributeValueValidator->validate($applicable, $submitted);
        $normalized = $this->attributeValueNormalizer->normalize($applicable, $validated);

        $current = $opportunity->attribute_values ?? [];
        $merged = array_merge($current, $normalized);

        if ($merged === $current) {
            return;
        }

        $old['attribute_values'] = $current;
        // `attribute_values` is NOT in Opportunity::$fillable (D-4 mass-
        // assignment guard): forceFill is the deliberate, single write path.
        $opportunity->forceFill(['attribute_values' => $merged]);
        $changed['attribute_values'] = $merged;
    }

    /**
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function logOperationalChange(Opportunity $opportunity, User $actor, array $changed, array $old): void
    {
        if ($changed === []) {
            return;
        }

        activity($opportunity->getTable())
            ->performedOn($opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management work update');
    }

    /**
     * @return Collection<int, OpportunityWorkflowStatus>
     */
    private function resolveWorkflowStatuses(Opportunity $opportunity): Collection
    {
        return $this->workflowResolver->statusesFor($this->workflowResolver->resolve($opportunity));
    }
}
