<?php

namespace App\Services;

use App\DataObjects\EaSectors\CreateEaSectorData;
use App\DataObjects\EaSectors\UpdateEaSectorData;
use App\Models\EaSector;
use App\Services\EaSectors\EaSectorHierarchy;
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

            if ($data->hasTagIds()) {
                $sector->tags()->sync($data->tagIds);
            }

            return $sector->fresh(['parent', 'tags']);
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

            if ($data->hasTagIds()) {
                $sector->tags()->sync($data->tagIds);
            }

            return $sector->fresh(['parent', 'tags']);
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
