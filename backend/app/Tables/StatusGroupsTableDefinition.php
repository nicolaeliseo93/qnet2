<?php

namespace App\Tables;

use App\Models\StatusGroup;
use App\Models\User;
use App\Services\StatusGroupService;
use App\Tables\StatusGroups\StatusGroupAdvancedFilterCatalog;
use App\Tables\StatusGroups\StatusGroupColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `status-groups` domain (spec 0039, D-6).
 *
 * All columns (name, color, sort_order, created_at) are real DB columns
 * handled entirely by the generic engine — no derived column, mirroring
 * LeadStatusesTableDefinition.
 */
class StatusGroupsTableDefinition extends AbstractTableDefinition
{
    public function __construct(private readonly StatusGroupService $service) {}

    public function domain(): string
    {
        return 'status-groups';
    }

    /**
     * @return class-string<StatusGroup>
     */
    public function modelClass(): string
    {
        return StatusGroup::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives StatusGroupPolicy::viewAny
    // from modelClass() (status-groups.viewAny).

    /**
     * @return Builder<StatusGroup>
     */
    public function baseQuery(): Builder
    {
        return StatusGroup::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return StatusGroupColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return StatusGroupColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return StatusGroupColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return StatusGroupAdvancedFilterCatalog::advancedFilters();
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
     * Map a StatusGroup to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var StatusGroup $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via StatusGroupPolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        if (Gate::forUser($actor)->allows('viewActivity', $row)) {
            $allowed[] = 'activity';
        }

        return $allowed;
    }

    /**
     * Delegate to StatusGroupService::delete() so the generic bulk-delete
     * endpoint respects the same 409 referenced-by-a-status guard as the
     * single DELETE /status-groups/{statusGroup} endpoint.
     */
    public function deleteModel(Model $model): void
    {
        /** @var StatusGroup $model */
        $this->service->delete($model);
    }
}
