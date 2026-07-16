<?php

namespace App\Tables\Statuses;

use App\Models\LeadStatus;
use App\Models\PipelineStatus;
use App\Models\StatusGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the `status_group` derived column (spec 0039, D-7) shared by both
 * status configurators' TableDefinition (pipeline-statuses, lead-statuses):
 * no real DB column of its own on the row (the related group's {id, name,
 * color}), mirroring BusinessFunctionParentColumn's derived-column pattern.
 * Not sortable/set-filterable at the basic column level (deliberately
 * declared non-filterable in both ColumnCatalog, like `color`) — reachable
 * ONLY via the advanced-filter Text match on the group's name, applied here.
 */
final class StatusGroupColumn
{
    /**
     * @return array{id: int, name: string, color: string|null}|null
     */
    public function summarize(PipelineStatus|LeadStatus $status): ?array
    {
        /** @var StatusGroup|null $statusGroup */
        $statusGroup = $status->statusGroup;

        if ($statusGroup === null) {
            return null;
        }

        return ['id' => $statusGroup->id, 'name' => $statusGroup->name, 'color' => $statusGroup->color];
    }

    /**
     * Advanced-filter Text match (spec 0039) on the related group's NAME via
     * a bound `whereHas`, mirroring BusinessFunctionParentColumn::applyFilter.
     *
     * @param  Builder<Model>  $query
     */
    public function applyAdvancedFilter(Builder $query, string $value): void
    {
        $query->whereHas('statusGroup', function (Builder $relatedQuery) use ($value): void {
            $relatedQuery->where('name', 'like', '%'.$this->escapeLike($value).'%');
        });
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
