<?php

namespace App\Tables\Roles;

use App\Tables\Concerns\UnwrapsMultiFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `users_count` AGGREGATE column on the `roles` table (no real DB column
 * — resolved via `baseQuery()`'s `withCount('users')`).
 *
 * Extracted out of RolesTableDefinition (file-size split, engineering.md §6):
 * the Excel-like distinct-values resolution and the derived filter (a `multi`
 * widget — Set + condition, spec 0004/0005) for this one column live in a
 * single focused file, mirroring the users-domain collaborators (UserGeoColumns
 * / UserPersonalDataColumns).
 */
class RoleUsersCountColumn
{
    use UnwrapsMultiFilter;

    /**
     * Maximum number of counts honoured in the Set sub-filter (multi widget).
     * Caps the WHERE cardinality (defence in depth).
     */
    private const int MAX_FILTER_VALUES = 100;

    /**
     * Excel-like distinct values (spec 0004/0005): `$query` already carries
     * the `users_count` alias (baseQuery() applies withCount('users')) and
     * every cross-column filter. Wrapping it as a derived table lets us
     * DISTINCT on that alias without re-aggregating or touching a real column
     * that doesn't exist. Search narrows on the count's string representation,
     * bound + LIKE-escaped.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $counts = DB::query()->fromSub($query, 'roles_with_count')->select('users_count')->distinct();

        if ($search !== null && $search !== '') {
            $counts->where('users_count', 'like', '%'.$this->escapeLike($search).'%');
        }

        return $counts
            ->orderBy('users_count')
            ->limit($limit)
            ->pluck('users_count')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * `users_count` is exposed through a `multi` widget (Set + condition, spec
     * 0004/0005): unwrap it and apply BOTH sub-models in AND. The Set
     * sub-model matches exact counts (the same strings distinctValues()
     * returns); the condition sub-model (equals/range/comparisons) is applied
     * as before this collaborator existed.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, array $filter): bool
    {
        $this->dispatchDerivedFilter(
            $filter,
            fn (array $set): mixed => $this->applySet($query, $set),
            fn (array $condition): mixed => $this->applyCondition($query, $condition),
        );

        return true;
    }

    /**
     * SET sub-model: the selected values are exact counts. Applied as an OR
     * of count comparisons on the `users` relation via has() — a portable,
     * driver-agnostic approach (no HAVING/alias dependency). Non-numeric
     * values are dropped; cardinality is capped.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applySet(Builder $query, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values) || $values === []) {
            return;
        }

        $counts = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_numeric($value),
        )), 0, self::MAX_FILTER_VALUES);

        if ($counts === []) {
            return;
        }

        $query->where(function (Builder $match) use ($counts): void {
            foreach ($counts as $count) {
                $match->orHas('users', '=', max(0, (int) $count));
            }
        });
    }

    /**
     * CONDITION sub-model (equals/notEqual/greaterThan(OrEqual)/lessThan
     * (OrEqual)/inRange). Applied as a count comparison on the `users`
     * relation via has() — a bound correlated-subquery WHERE that is portable
     * across drivers and respected by the (clone)->count() total. AG Grid
     * number operators map to SQL comparisons; `inRange` becomes a closed
     * interval. Non-numeric/blank payloads add no constraint.
     *
     * The relation resolves off the guard-pinned model from
     * RolesTableDefinition::baseQuery() (Spatie's guard-aware users()
     * relation), so the sanctum default-guard pitfall documented there does
     * not apply here either.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyCondition(Builder $query, array $filter): void
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';

        if ($type === 'inRange') {
            $from = $this->intOrNull($filter['filter'] ?? null);
            $to = $this->intOrNull($filter['filterTo'] ?? null);

            if ($from !== null) {
                $query->has('users', '>=', $from);
            }

            if ($to !== null) {
                $query->has('users', '<=', $to);
            }

            return;
        }

        $value = $this->intOrNull($filter['filter'] ?? null);

        if ($value === null) {
            return; // blank / notBlank / malformed → no constraint
        }

        $operator = match ($type) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=', // 'equals'
        };

        $query->has('users', $operator, $value);
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
     * Mirrors TableService/FilterApplier's escapeLike (kept local to avoid
     * widening those classes' API — same convention as UserPersonalDataColumns).
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
