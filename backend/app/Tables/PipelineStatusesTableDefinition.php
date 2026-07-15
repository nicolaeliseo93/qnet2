<?php

namespace App\Tables;

use App\Models\PipelineStatus;
use App\Models\User;
use App\Tables\PipelineStatuses\PipelineStatusAdvancedFilterCatalog;
use App\Tables\PipelineStatuses\PipelineStatusColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `pipeline-statuses` domain (spec 0023).
 *
 * All columns (name, color, sort_order, created_at) are real DB columns
 * handled entirely by the generic engine — no derived column, mirroring
 * SourcesTableDefinition.
 */
class PipelineStatusesTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'pipeline-statuses';
    }

    /**
     * @return class-string<PipelineStatus>
     */
    public function modelClass(): string
    {
        return PipelineStatus::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives PipelineStatusPolicy::viewAny
    // from modelClass() (pipeline-statuses.viewAny).

    /**
     * @return Builder<PipelineStatus>
     */
    public function baseQuery(): Builder
    {
        return PipelineStatus::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return PipelineStatusColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return PipelineStatusColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return PipelineStatusColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return PipelineStatusAdvancedFilterCatalog::advancedFilters();
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
     * Map a PipelineStatus to the row payload. `actions` is attached by the
     * generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var PipelineStatus $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'color' => $row->color,
            'sort_order' => $row->sort_order,
            'created_at' => $row->created_at,
        ];
    }

    /**
     * Allowed action keys for a single row, via PipelineStatusPolicy.
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
