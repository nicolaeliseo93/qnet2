<?php

namespace App\Tables\Campaigns;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `campaigns` domain's doubly-derived `project_status` column
 * (BR-2/AC-032), extracted out of CampaignsTableDefinition (file-size split,
 * engineering.md §6): a linked campaign always has its OWN
 * `project_status_id` NULL, so its EFFECTIVE status is the campaign's own
 * when standalone, else read through the linked project's — for a single row
 * (mapRow), for the Set Filter (bound nested whereHas, no raw SQL) and for
 * the Excel-like distinct value list.
 */
final class CampaignProjectStatusResolver
{
    /**
     * The campaign's EFFECTIVE project_status: its own when standalone, else
     * read through the linked project's (relations must already be
     * eager-loaded by the caller's baseQuery()).
     */
    public function effectiveStatus(Campaign $row): ?Model
    {
        return $row->projectStatus ?? $row->project?->projectStatus;
    }

    /**
     * Match campaigns whose EFFECTIVE project_status name is among $names —
     * own status (standalone) OR the linked project's, combined via a nested
     * `whereHas('project.projectStatus', ...)` (bound parameters only).
     *
     * @param  Builder<Campaign>  $query
     * @param  array<int, string>  $names
     */
    public function applyFilter(Builder $query, array $names): void
    {
        if ($names === []) {
            return;
        }

        $query->where(function (Builder $group) use ($names): void {
            $group->whereHas('projectStatus', static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            })->orWhereHas('project.projectStatus', static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        });
    }

    /**
     * Distinct EFFECTIVE project_status names among the campaigns matching
     * $query (already scoped by every other active filter): own status ids
     * (standalone) union the linked projects' status ids.
     *
     * @param  Builder<Campaign>  $query
     * @return array<int, string>
     */
    public function distinctValues(?string $search, Builder $query, int $limit): array
    {
        $ownStatusIds = (clone $query)->whereNotNull('project_status_id')->select('project_status_id');
        $linkedProjectIds = (clone $query)->whereNull('project_status_id')->whereNotNull('project_id')->pluck('project_id');

        $linkedStatusIds = DB::table('projects')
            ->whereIn('id', $linkedProjectIds)
            ->whereNotNull('project_status_id')
            ->pluck('project_status_id');

        return DB::table('project_statuses')
            ->where(function ($builder) use ($ownStatusIds, $linkedStatusIds): void {
                $builder->whereIn('id', $ownStatusIds)->orWhereIn('id', $linkedStatusIds);
            })
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
