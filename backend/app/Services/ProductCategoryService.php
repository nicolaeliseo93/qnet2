<?php

namespace App\Services;

use App\DataObjects\ProductCategories\CreateProductCategoryData;
use App\DataObjects\ProductCategories\UpdateProductCategoryData;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `product-categories` resource (spec 0017):
 * create/update (including the own-attributes full-replace sync and the
 * anti-cycle guard on `parent_id`), a restrictive delete, and the read-side
 * tree/effective-attributes/inherited-attributes views (delegated to
 * CategoryHierarchy). The controller stays thin; this Service is the single
 * authority.
 */
class ProductCategoryService
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    public function create(CreateProductCategoryData $data): ProductCategory
    {
        return DB::transaction(function () use ($data): ProductCategory {
            /** @var ProductCategory $category */
            $category = ProductCategory::create([
                'name' => $data->name,
                'parent_id' => $data->parentId,
                'description' => $data->description,
            ]);

            if ($data->hasAttributes()) {
                $this->syncAttributes($category, $data->attributes);
            }

            return $category->fresh(['parent', 'attributes']);
        });
    }

    public function update(ProductCategory $category, UpdateProductCategoryData $data): ProductCategory
    {
        if ($data->hasParentId() && $data->parentId !== null) {
            $this->assertNoCycle($category, $data->parentId);
        }

        return DB::transaction(function () use ($category, $data): ProductCategory {
            $attributes = $data->submittedAttributes();

            if ($attributes !== []) {
                $category->update($attributes);
            }

            if ($data->hasAttributes()) {
                $this->syncAttributes($category, $data->attributes);
            }

            return $category->fresh(['parent', 'attributes']);
        });
    }

    /**
     * Restrictive delete: a category with child categories or associated
     * products cannot be removed (it would silently orphan them).
     */
    public function delete(ProductCategory $category): void
    {
        if ($category->children()->exists() || $category->products()->exists()) {
            abort(409, 'This category has child categories or products and cannot be deleted.');
        }

        $category->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        return $this->hierarchy->tree();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function effectiveAttributes(ProductCategory $category): Collection
    {
        return $this->hierarchy->effectiveAttributes($category);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function inheritedAttributes(ProductCategory $category): Collection
    {
        return $this->hierarchy->ancestorAttributes($category);
    }

    /**
     * $parentId may not be $category itself, nor one of its own descendants
     * (i.e. $category may not be an ancestor of the prospective new parent) —
     * either would create a cycle in the tree.
     */
    private function assertNoCycle(ProductCategory $category, int $parentId): void
    {
        if ($parentId === $category->id) {
            abort(422, 'A category cannot be its own parent.');
        }

        $parent = ProductCategory::find($parentId);

        if ($parent !== null && $this->hierarchy->isAncestorOf($parent, $category->id)) {
            abort(422, 'A category cannot be moved under one of its own descendants.');
        }
    }

    /**
     * Full-replace sync of the category's OWN attribute assignments (pivot:
     * is_required/sort_order), mirroring RoleService's field-permission sync
     * pattern: delete-then-recreate semantics via Eloquent's sync().
     *
     * @param  array<int, array{attribute_id: int, is_required?: bool, sort_order?: int}>  $attributes
     */
    private function syncAttributes(ProductCategory $category, array $attributes): void
    {
        $syncData = [];

        foreach ($attributes as $row) {
            $syncData[(int) $row['attribute_id']] = [
                'is_required' => (bool) ($row['is_required'] ?? false),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        $category->attributes()->sync($syncData);
    }
}
