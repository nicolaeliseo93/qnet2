<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `request-management` resource (spec 0049).
 *
 * The module has no dedicated model (it operates on Opportunity, D-1): the
 * abstract's contract only needs a resource key + field/action catalogue, no
 * Eloquent class, so this mirrors the smallest existing authorizations
 * (VatRatesAuthorization) with the operative fields the work panel writes
 * (D-4/D-5, `next_callback_at` added by spec 0054 D-4) — visible+editable
 * when the actor may write, else read-only. None of the 3 is
 * mandatory-restrictive (spec 0049, meta endpoint contract): the panel never
 * blocks on a missing value here, the dedicated 422 rules live in
 * AttributeValueValidator/ValidatesWorkflowStatus.
 */
class RequestManagementAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'request-management';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('opportunity_workflow_status_id', 'select'),
            new FieldDefinition('attribute_values', 'custom'),
            // Spec 0054, D-4: written exclusively by
            // RequestManagementService::updateWork() (never mass-assigned —
            // Opportunity::$fillable deliberately excludes it, spec 0052
            // D-2); this catalogue entry only closes a gap in the per-field
            // permission system, it grants nothing new.
            new FieldDefinition('next_callback_at', 'date'),
            // "Prodotti di interesse" (user directive 2026-07-22): written by
            // RequestManagementService::updateWork() through
            // OpportunityProductInterestWriter; this entry only closes the
            // per-field permission gap, it grants nothing new.
            new FieldDefinition('products_of_interest', 'multiselect'),
            // Attribution block (user directive 2026-07-22): "Fonte",
            // "Segnalatore" and the GA2 "Operatore" — the same three
            // dimensions the opportunities form owns, made editable from the
            // work panel too. `operator_id` is NOT a column: it addresses the
            // `opportunity_user` pivot row at position
            // Opportunity::OPERATOR_MANAGER_POSITION (see
            // Opportunity::operatorManager()).
            new FieldDefinition('source_id', 'select'),
            new FieldDefinition('reporter_id', 'select'),
            new FieldDefinition('operator_id', 'select'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['export', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'opportunity_workflow_status_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'attribute_values' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'next_callback_at' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'products_of_interest' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'source_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'reporter_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'operator_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'export' => $actor->can('request-management.export'),
            // Gates the ActivityLogSection in the panel (spec 0034/0049 D-7);
            // the record-level `request-management.view` boundary is
            // enforced separately by GET /api/activity-log/request-management/{id}.
            'view_activity' => $model !== null && $actor->can('request-management.viewActivity'),
        ];
    }
}
