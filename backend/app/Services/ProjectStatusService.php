<?php

namespace App\Services;

use App\DataObjects\ProjectStatuses\CreateProjectStatusData;
use App\DataObjects\ProjectStatuses\UpdateProjectStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\ProjectStatus;
use Illuminate\Support\Collection;

/**
 * Business logic for the `project-statuses` resource (spec 0023): a full-CRUD
 * lookup entity (name/color/sort_order) shared by Projects and Campaigns. The
 * controller stays thin; this Service is the single authority, mirroring
 * SourceService's shape plus the BR-4 delete guard (ProductCategoryService's
 * abort(409) pattern).
 */
class ProjectStatusService
{
    public function create(CreateProjectStatusData $data): ProjectStatus
    {
        return ProjectStatus::create($data->attributes());
    }

    public function update(ProjectStatus $projectStatus, UpdateProjectStatusData $data): ProjectStatus
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $projectStatus->fill($attributes)->save();

        return $projectStatus->fresh();
    }

    /**
     * BR-4 (status-delete-guard): a status referenced by at least one Project
     * or Campaign cannot be removed (it would silently orphan them). Defense
     * in depth: the FK is also restrictOnDelete at the schema layer.
     */
    public function delete(ProjectStatus $projectStatus): void
    {
        if ($projectStatus->projects()->exists() || $projectStatus->campaigns()->exists()) {
            abort(409, 'This project status is used by a project or a campaign and cannot be deleted.');
        }

        $projectStatus->delete();
    }

    /**
     * Minimal, searchable, paginated project status list for the for-select
     * standard (ADR 0011), mirroring SourceService::forSelect. Ordered by
     * `sort_order` first so the select mirrors the table's display order.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = ProjectStatus::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, ProjectStatus> $page */
        $page = $base->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, ProjectStatus>  $page
     * @return Collection<int, ProjectStatus>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, ProjectStatus> $hydrated */
        $hydrated = ProjectStatus::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
