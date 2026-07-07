<?php

namespace App\Tables\ProductCategories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Shared resolution for an AGGREGATE `*_count` column on `product-categories`
 * (attributes_count / products_count, both resolved via baseQuery()'s
 * withCount()): no real DB column, so a raw WHERE on the alias is not
 * portable (MySQL cannot see a SELECT-list alias from WHERE — only ORDER BY/
 * GROUP BY do), while ORDER BY IS handled generically by the engine. Filter +
 * distinct-values are delegated here instead, via a relation-count condition
 * (`has()`), mirroring RoleUsersCountColumn. Parameterized by relation name
 * (method argument, not constructor) so ONE instance serves both count
 * columns — a plain `number` condition widget (equals/range/comparisons),
 * no Set sub-model (unlike RoleUsersCountColumn's richer `multi` widget —
 * not requested for this domain).
 */
final class ProductCategoryCountColumn
{
    /**
     * Excel-like distinct values (spec 0004/0005): `$query` already carries
     * the `$alias` column (baseQuery() applies withCount()) and every
     * cross-column filter. Wrapping it as a derived table lets us DISTINCT on
     * that alias without re-aggregating or touching a real column that
     * doesn't exist. Search narrows on the count's string representation,
     * bound + LIKE-escaped.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, string $alias, ?string $search, int $limit): array
    {
        $counts = DB::query()->fromSub($query, 'product_categories_with_counts')->select($alias)->distinct();

        if ($search !== null && $search !== '') {
            $counts->where($alias, 'like', '%'.$this->escapeLike($search).'%');
        }

        return $counts
            ->orderBy($alias)
            ->limit($limit)
            ->pluck($alias)
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * A plain `number` condition filter (equals/notEqual/greaterThan(OrEqual)/
     * lessThan(OrEqual)/inRange), applied as a count comparison on $relation
     * via has() — a bound correlated-subquery WHERE that is portable across
     * drivers and respected by the (clone)->count() total.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $relation, array $filter): bool
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';

        if ($type === 'inRange') {
            $from = $this->intOrNull($filter['filter'] ?? null);
            $to = $this->intOrNull($filter['filterTo'] ?? null);

            if ($from !== null) {
                $query->has($relation, '>=', $from);
            }

            if ($to !== null) {
                $query->has($relation, '<=', $to);
            }

            return true;
        }

        $value = $this->intOrNull($filter['filter'] ?? null);

        if ($value === null) {
            return true; // blank / notBlank / malformed → no constraint
        }

        $operator = match ($type) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=', // 'equals'
        };

        $query->has($relation, $operator, $value);

        return true;
    }

    /**
     * Coerce a filter payload value to a non-negative int, or null when it is
     * not a usable numeric value (so the filter adds no constraint).
     */
    private function intOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
