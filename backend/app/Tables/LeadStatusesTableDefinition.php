<?php

namespace App\Tables;

use App\Models\LeadStatus;
use App\Models\User;
use App\Services\LeadStatusService;
use App\Tables\LeadStatuses\LeadStatusAdvancedFilterCatalog;
use App\Tables\LeadStatuses\LeadStatusColumnCatalog;
use App\Tables\Statuses\StatusGroupColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `lead-statuses` domain (spec 0029).
 *
 * Real columns (name, color, sort_order, created_at) are handled entirely by
 * the generic engine. `status_group` (spec 0039, D-6/D-7) has no real DB
 * column of its own (the related group's {id, name, color}) and is DERIVED,
 * delegated to StatusGroupColumn — its only filter path is the advanced Text
 * match on the group's name (applyAdvancedFilter override below), mirroring
 * PipelineStatusesTableDefinition.
 */
class LeadStatusesTableDefinition extends AbstractTableDefinition
{
    /** Advanced-filter descriptor name for the derived `status_group` column. */
    private const string STATUS_GROUP_FILTER = 'status_group';

    public function __construct(
        private readonly LeadStatusService $service,
        private readonly StatusGroupColumn $statusGroupColumn,
    ) {}

    public function domain(): string
    {
        return 'lead-statuses';
    }

    /**
     * @return class-string<LeadStatus>
     */
    public function modelClass(): string
    {
        return LeadStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives LeadStatusPolicy::viewAny
    // from modelClass() (lead-statuses.viewAny).

    /**
     * @return Builder<LeadStatus>
     */
    public function baseQuery(): Builder
    {
        // Eager-load statusGroup (spec 0039) to avoid N+1 on the derived
        // `status_group` column.
        return LeadStatus::query()->with('statusGroup');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return LeadStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return LeadStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return LeadStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return LeadStatusAdvancedFilterCatalog::advancedFilters();
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
     * Map a LeadStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var LeadStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            // spec 0039: the two mandatory system rows (D-2) and their
            // optional classification (D-6).
            'system_key' => $row->system_key,
            'status_group' => $this->statusGroupColumn->summarize($row),
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via LeadStatusPolicy. `delete`
     * is OMITTED for a system row (spec 0039, D-2 — never deletable);
     * `edit` REMAINS (name/color are still editable).
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var LeadStatus $row */
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
     * Handle the derived `status_group` advanced filter (Text match on the
     * group's name); every other name (a real column) falls through to the
     * generic engine.
     *
     * @param  Builder<LeadStatus>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        if ($name === self::STATUS_GROUP_FILTER) {
            if (is_string($value) && $value !== '') {
                $this->statusGroupColumn->applyAdvancedFilter($query, $value);
            }

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }

    /**
     * Delegate to LeadStatusService::delete() so the generic bulk-delete
     * endpoint respects the SAME guards (system-row protection spec 0039 D-2,
     * BR-3 referenced-by guard) as the single DELETE
     * /lead-statuses/{leadStatus} endpoint (AC-003).
     */
    public function deleteModel(Model $model): void
    {
        /** @var LeadStatus $model */
        $this->service->delete($model);
    }
}
