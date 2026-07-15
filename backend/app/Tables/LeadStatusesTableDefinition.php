<?php

namespace App\Tables;

use App\Models\LeadStatus;
use App\Models\User;
use App\Tables\LeadStatuses\LeadStatusAdvancedFilterCatalog;
use App\Tables\LeadStatuses\LeadStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `lead-statuses` domain (spec 0029).
 *
 * All columns (name, color, sort_order, created_at) are real DB columns
 * handled entirely by the generic engine — no derived column, mirroring
 * PipelineStatusesTableDefinition.
 */
class LeadStatusesTableDefinition extends AbstractTableDefinition
{
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
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via LeadStatusPolicy.
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

        return $allowed;
    }
}
