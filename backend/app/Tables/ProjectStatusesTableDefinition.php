<?php

namespace App\Tables;

use App\Models\ProjectStatus;
use App\Models\User;
use App\Tables\ProjectStatuses\ProjectStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `project-statuses` domain (spec 0023).
 *
 * All columns (name, color, sort_order, created_at) are real DB columns
 * handled entirely by the generic engine — no derived column, mirroring
 * SourcesTableDefinition.
 */
class ProjectStatusesTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'project-statuses';
    }

    /**
     * @return class-string<ProjectStatus>
     */
    public function modelClass(): string
    {
        return ProjectStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives ProjectStatusPolicy::viewAny
    // from modelClass() (project-statuses.viewAny).

    /**
     * @return Builder<ProjectStatus>
     */
    public function baseQuery(): Builder
    {
        return ProjectStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return ProjectStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return ProjectStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return ProjectStatusColumnCatalog::actions();
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
     * Map a ProjectStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ProjectStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via ProjectStatusPolicy.
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
