<?php

namespace App\Services;

use App\DataObjects\EaSectors\CreateEaSectorData;
use App\DataObjects\EaSectors\UpdateEaSectorData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\EaSector;
use App\Services\EaSectors\EaSectorHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `ea-sectors` resource (spec 0018): create/update
 * (including the anti-cycle guard on `parent_id`), a restrictive delete
 * (children only — no other relation exists yet), and the read-side tree
 * (delegated to EaSectorHierarchy). The controller stays thin; this
 * Service is the single authority.
 */
class EaSectorService
{
    public function __construct(private readonly EaSectorHierarchy $hierarchy) {}

    public function create(CreateEaSectorData $data): EaSector
    {
        return DB::transaction(function () use ($data): EaSector {
            /** @var EaSector $sector */
            $sector = EaSector::create([
                'name' => $data->name,
                'parent_id' => $data->parentId,
            ]);

            return $sector->fresh(['parent']);
        });
    }

    public function update(EaSector $sector, UpdateEaSectorData $data): EaSector
    {
        if ($data->hasParentId() && $data->parentId !== null) {
            $this->assertNoCycle($sector, $data->parentId);
        }

        return DB::transaction(function () use ($sector, $data): EaSector {
            $attributes = $data->submittedAttributes();

            if ($attributes !== []) {
                $sector->update($attributes);
            }

            return $sector->fresh(['parent']);
        });
    }

    /**
     * Restrictive delete: a sector with child sectors cannot be removed (it
     * would silently orphan them). No other relation exists yet (spec 0018
     * scope), unlike ProductCategoryService's product guard.
     */
    public function delete(EaSector $sector): void
    {
        if ($sector->children()->exists()) {
            abort(409, 'This sector has sub-sectors and cannot be deleted.');
        }

        $sector->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        return $this->hierarchy->tree();
    }

    /**
     * Minimal, searchable, paginated EA-sector list for the for-select
     * standard (ADR 0011, spec 0020), mirroring SourceService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = EaSector::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, EaSector> $page */
        $page = $base->orderBy('name')
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
     * @param  Collection<int, EaSector>  $page
     * @return Collection<int, EaSector>
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

        /** @var Collection<int, EaSector> $hydrated */
        $hydrated = EaSector::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * $parentId may not be $sector itself, nor one of its own descendants
     * (i.e. $sector may not be an ancestor of the prospective new parent) —
     * either would create a cycle in the tree.
     */
    private function assertNoCycle(EaSector $sector, int $parentId): void
    {
        if ($parentId === $sector->id) {
            abort(422, 'A sector cannot be its own parent.');
        }

        $parent = EaSector::find($parentId);

        if ($parent !== null && $this->hierarchy->isAncestorOf($parent, $sector->id)) {
            abort(422, 'A sector cannot be moved under one of its own descendants.');
        }
    }
}
