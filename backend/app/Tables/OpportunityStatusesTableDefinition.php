<?php

namespace App\Tables;

use App\Models\OpportunityStatus;
use App\Models\User;
use App\Services\OpportunityStatusService;
use App\Tables\OpportunityStatuses\OpportunityStatusAdvancedFilterCatalog;
use App\Tables\OpportunityStatuses\OpportunityStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `opportunity-statuses` domain (spec 0043).
 *
 * Every column (name, color, sort_order, group, created_at) is a real DB
 * column handled entirely by the generic engine.
 */
class OpportunityStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly OpportunityStatusService $service) {}

    public function domain(): string
    {
        return 'opportunity-statuses';
    }

    /**
     * @return class-string<OpportunityStatus>
     */
    public function modelClass(): string
    {
        return OpportunityStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives OpportunityStatusPolicy::viewAny
    // from modelClass() (opportunity-statuses.viewAny).

    /**
     * @return Builder<OpportunityStatus>
     */
    public function baseQuery(): Builder
    {
        return OpportunityStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return OpportunityStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return OpportunityStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return OpportunityStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return OpportunityStatusAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'sort_order', 'direction' => 'asc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Map an OpportunityStatus to the row payload. `actions` is attached by
     * the generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var OpportunityStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            // spec 0043: the mandatory system rows (D-2) and the fixed
            // 3-value classification (`group`).
            'system_key' => $row->system_key,
            'group' => $row->group->value,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via OpportunityStatusPolicy.
     * `delete` is OMITTED for a system row (spec 0043, D-2 — never
     * deletable); `edit` REMAINS (name/color are still editable).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var OpportunityStatus $row */
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (! $row->isSystem() && Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to OpportunityStatusService::delete() so the generic
     * bulk-delete endpoint respects the SAME guards (system-row protection
     * spec 0043 D-2, BR-2 referenced-by guard) as the single DELETE
     * /opportunity-statuses/{opportunityStatus} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var OpportunityStatus $model */
        $this->service->delete($model);
    }
}
