<?php

namespace App\Tables;

use App\Models\LeadStatus;
use App\Models\User;
use App\Services\LeadStatusService;
use App\Tables\LeadStatuses\LeadStatusAdvancedFilterCatalog;
use App\Tables\LeadStatuses\LeadStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `lead-statuses` domain (spec 0029).
 *
 * Every column (name, color, sort_order, group, created_at) is a real DB
 * column handled entirely by the generic engine — `group` (spec 0039 pivot,
 * App\Enums\StatusGroup) replaced the earlier derived `status_group` column
 * (a lookup FK), so it needs no eager-load/derived-column plumbing anymore.
 */
class LeadStatusesTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly LeadStatusService $service) {}

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
        return LeadStatus::query();
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
            // spec 0039: the mandatory system rows (D-2) and the fixed
            // 3-value classification (`group`).
            'system_key' => $row->system_key,
            'group' => $row->group->value,
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
