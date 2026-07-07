<?php

namespace App\Services\ProductCategories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * Read-side resolution of the product-category tree (spec 0017): ancestor
 * chains, a category's effective (own UNION inherited) attributes, the
 * ancestor-only attribute list used by the show endpoint, and the full
 * nested tree. Walks `parent_id` in PHP rather than a raw recursive SQL
 * query (correctness/portability over cleverness — works identically on the
 * SQLite dev/test driver and MySQL production).
 */
final class CategoryHierarchy
{
    /**
     * Defensive cap on the ancestor walk: the write-side anti-cycle guard
     * (ProductCategoryService) prevents a real cycle from ever being
     * persisted, so this only guards against corrupted data looping forever.
     */
    private const int MAX_DEPTH = 100;

    /**
     * $category's ancestors, ROOT-FIRST (does not include $category itself).
     * This is the STRUCTURAL walk — it ignores `inherits_attributes` and is
     * used only by the anti-cycle guard, which must see the full chain
     * regardless of any inheritance barrier.
     *
     * @return Collection<int, ProductCategory>
     */
    public function ancestors(ProductCategory $category): Collection
    {
        $chain = [];
        $currentId = $category->parent_id;
        $depth = 0;

        while ($currentId !== null && $depth < self::MAX_DEPTH) {
            $parent = ProductCategory::find($currentId);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;
            $currentId = $parent->parent_id;
            $depth++;
        }

        return collect(array_reverse($chain));
    }

    /**
     * The ancestors $category actually INHERITS from, ROOT-FIRST — the
     * structural walk truncated at the first inheritance barrier. A node whose
     * `inherits_attributes` is false does not pull in its own parent, so the
     * walk stops there: if $category itself opts out, this is empty; otherwise
     * it climbs while each node keeps inheriting, cutting off everything above
     * the first opted-out ancestor (that ancestor's OWN attributes still count,
     * as it is a direct ancestor $category inherits).
     *
     * @return Collection<int, ProductCategory>
     */
    private function inheritedAncestors(ProductCategory $category): Collection
    {
        if (! $category->inherits_attributes) {
            return collect();
        }

        $chain = [];
        $node = $category;
        $depth = 0;

        while ($node->parent_id !== null && $depth < self::MAX_DEPTH) {
            $parent = ProductCategory::find($node->parent_id);

            if ($parent === null) {
                break;
            }

            $chain[] = $parent;

            // Barrier: this ancestor contributes its own attributes but, having
            // opted out, pulls nothing further up — stop climbing.
            if (! $parent->inherits_attributes) {
                break;
            }

            $node = $parent;
            $depth++;
        }

        return collect(array_reverse($chain));
    }

    /**
     * Whether $candidateAncestorId is among $category's ancestors — the
     * anti-cycle check: reparenting $category under a node that descends
     * from $category would create a cycle.
     */
    public function isAncestorOf(ProductCategory $category, int $candidateAncestorId): bool
    {
        return $this->ancestors($category)->contains('id', $candidateAncestorId);
    }

    /**
     * $category's EFFECTIVE attributes: its own assignments UNION those of the
     * ancestors it actually inherits from (see inheritedAncestors — the chain
     * is cut at the first `inherits_attributes = false` node), root-first
     * (AC-008). When the same attribute is assigned at
     * multiple levels, the MOST SPECIFIC one wins (own overrides an ancestor,
     * a closer ancestor overrides a farther one) — but its position in the
     * output stays where it FIRST appeared walking root→self, so the overall
     * order remains "ancestors first, then by sort_order" even after an
     * override.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function effectiveAttributes(ProductCategory $category): Collection
    {
        $chain = $this->inheritedAncestors($category)->push($category);

        $ordered = [];
        $index = [];

        foreach ($chain as $level) {
            $isOwn = $level->is($category);

            foreach ($this->ownAttributeRows($level) as $attribute) {
                $entry = [
                    'id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'data_type' => $attribute->data_type,
                    'is_required' => (bool) $attribute->pivot->is_required,
                    'sort_order' => (int) $attribute->pivot->sort_order,
                    'inherited' => ! $isOwn,
                    'options' => $this->optionsFor($attribute),
                ];

                if (isset($index[$attribute->id])) {
                    $ordered[$index[$attribute->id]] = $entry;
                } else {
                    $ordered[] = $entry;
                    $index[$attribute->id] = array_key_last($ordered);
                }
            }
        }

        return collect(array_values($ordered));
    }

    /**
     * The attributes owned by the ANCESTORS $category inherits from (deduped, a
     * closer ancestor wins; empty when $category opts out of inheritance), for
     * the show endpoint's read-only `inherited_attributes` side list — never
     * merged with $category's own assignments.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ancestorAttributes(ProductCategory $category): Collection
    {
        $ordered = [];
        $index = [];

        foreach ($this->inheritedAncestors($category) as $ancestor) {
            foreach ($this->ownAttributeRows($ancestor) as $attribute) {
                $entry = [
                    'attribute_id' => $attribute->id,
                    'code' => $attribute->code,
                    'name' => $attribute->name,
                    'data_type' => $attribute->data_type,
                    'is_required' => (bool) $attribute->pivot->is_required,
                ];

                if (isset($index[$attribute->id])) {
                    $ordered[$index[$attribute->id]] = $entry;
                } else {
                    $ordered[] = $entry;
                    $index[$attribute->id] = array_key_last($ordered);
                }
            }
        }

        return collect(array_values($ordered));
    }

    /**
     * The full category tree, roots first, each node carrying its own
     * attributes/products counts (spec 0017 tree endpoint).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tree(): array
    {
        $byParent = ProductCategory::query()
            ->withCount(['attributes', 'products'])
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        return $this->buildNodes($byParent, null);
    }

    /**
     * @param  Collection<int|string, Collection<int, ProductCategory>>  $byParent
     * @return array<int, array<string, mixed>>
     */
    private function buildNodes(Collection $byParent, ?int $parentId): array
    {
        $nodes = [];

        /** @var Collection<int, ProductCategory> $children */
        $children = $byParent->get($parentId ?? '', collect());

        foreach ($children as $category) {
            $nodes[] = [
                'id' => $category->id,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'children' => $this->buildNodes($byParent, $category->id),
                'attributes_count' => (int) $category->attributes_count,
                'products_count' => (int) $category->products_count,
            ];
        }

        return $nodes;
    }

    /**
     * $level's OWN attribute assignments (pivot + attribute eager-loaded),
     * ordered by the pivot's sort_order.
     *
     * @return Collection<int, Attribute>
     */
    private function ownAttributeRows(ProductCategory $level): Collection
    {
        return $level->attributes()->with('options')->orderBy('attribute_category.sort_order')->get();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function optionsFor(Attribute $attribute): array
    {
        if ($attribute->data_type !== AttributeType::Enum) {
            return [];
        }

        return $attribute->options->map(static fn ($option): array => [
            'value' => $option->value,
            'label' => $option->label,
        ])->all();
    }
}
