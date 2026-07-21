<?php

namespace App\Support\Import;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SSRM query for POST /api/imports/{domain}/{importRun}/rows (spec 0033,
 * AC-016): pages the run's staged `import_run_rows`, with sort/filter/search
 * driven ENTIRELY by a server-side allow-list — never the raw AG Grid colId
 * reaching a whereRaw/orderByRaw directly. The allow-list is `row_number` +
 * `status` (real columns) plus the definition's OWN `fields()` ids, resolved
 * against `mapped_values` via Eloquent's native `column->path` JSON operator
 * (portable across MySQL/SQLite, unlike a hand-rolled JSON_EXTRACT/JSON_UNQUOTE
 * raw expression) — the colId is only ever compared with `in_array(...,
 * true)` before being embedded in that path, never taken from raw input.
 * Any colId outside the allow-list is silently ignored (no 422 in the frozen
 * data_contract for this endpoint — the request is still served, just
 * without that particular sort/filter applied), mirroring how TableRowsRequest
 * rejects instead of executing unsafe SQL.
 */
final class ReviewRowsQuery
{
    /** Real `import_run_rows` columns always sortable/filterable. */
    private const array BASE_SORTABLE = ['row_number', 'status'];

    private const array BASE_FILTERABLE = ['row_number', 'status', 'is_edited', 'duplicate_of_id'];

    /**
     * @param  array<int, string>  $fieldIds  the definition's mappable field ids (allow-list extension)
     * @param  array<string, mixed>  $params  raw SSRM payload (startRow/endRow/sortModel/filterModel/search)
     * @return array{items: Collection<int, ImportRunRow>, total: int, offset: int, limit: int}
     */
    public function paginate(ImportRun $run, array $fieldIds, array $params): array
    {
        $startRow = max(0, (int) ($params['startRow'] ?? 0));
        $endRow = max($startRow + 1, (int) ($params['endRow'] ?? $startRow + BaseApiController::MAX_LIMIT));
        $limit = min(BaseApiController::MAX_LIMIT, $endRow - $startRow);

        $query = ImportRunRow::query()->where('import_run_id', $run->id)->with([
            'operator',
            'operationalSite.addresses' => fn ($addressQuery) => $addressQuery->with('city:id,name'),
        ]);

        $this->applyFilters($query, $params['filterModel'] ?? null, [...self::BASE_FILTERABLE, ...$fieldIds], $fieldIds);
        $this->applySearch($query, $params['search'] ?? null);
        $this->applySort($query, $params['sortModel'] ?? [], [...self::BASE_SORTABLE, ...$fieldIds], $fieldIds);

        $total = (clone $query)->count();

        /** @var Collection<int, ImportRunRow> $items */
        $items = $query->orderBy('row_number')->skip($startRow)->take($limit)->get();

        return ['items' => $items, 'total' => $total, 'offset' => $startRow, 'limit' => $limit];
    }

    /**
     * @param  array<int, string>  $allowedSort
     * @param  array<int, string>  $fieldIds
     * @param  array<int, array{colId?: mixed, sort?: mixed}>  $sortModel
     */
    private function applySort(Builder $query, mixed $sortModel, array $allowedSort, array $fieldIds): void
    {
        if (! is_array($sortModel)) {
            return;
        }

        foreach ($sortModel as $sort) {
            $colId = is_array($sort) ? ($sort['colId'] ?? null) : null;
            $direction = is_array($sort) && strtolower((string) ($sort['sort'] ?? '')) === 'desc' ? 'desc' : 'asc';

            if (! is_string($colId) || ! in_array($colId, $allowedSort, true)) {
                continue;
            }

            $query->orderBy(
                in_array($colId, self::BASE_SORTABLE, true) ? $colId : "mapped_values->{$colId}",
                $direction,
            );
        }
    }

    /**
     * @param  array<int, string>  $allowedFilter
     * @param  array<int, string>  $fieldIds
     */
    private function applyFilters(Builder $query, mixed $filterModel, array $allowedFilter, array $fieldIds): void
    {
        if (! is_array($filterModel)) {
            return;
        }

        foreach ($filterModel as $colId => $filter) {
            if (! is_string($colId) || ! in_array($colId, $allowedFilter, true)) {
                continue;
            }

            $value = is_array($filter) ? ($filter['filter'] ?? null) : $filter;

            if ($value === null || $value === '') {
                continue;
            }

            $this->applyOneFilter($query, $colId, $value, $fieldIds);
        }
    }

    /**
     * @param  array<int, string>  $fieldIds
     */
    private function applyOneFilter(Builder $query, string $colId, mixed $value, array $fieldIds): void
    {
        match (true) {
            $colId === 'status' => $query->where('status', (string) $value),
            $colId === 'is_edited' => $query->where('is_edited', filter_var($value, FILTER_VALIDATE_BOOLEAN)),
            $colId === 'duplicate_of_id' => $query->where('duplicate_of_id', (int) $value),
            $colId === 'row_number' => $query->where('row_number', (int) $value),
            in_array($colId, $fieldIds, true) => $query->where(
                "mapped_values->{$colId}",
                'like',
                '%'.$this->escapeLike((string) $value).'%',
            ),
            default => null,
        };
    }

    /**
     * Portable "search everything" fallback: the JSON blob cast to text
     * differs by driver (MySQL: CHAR, SQLite: TEXT) — the ONLY spot left
     * needing a raw expression, and the driver name (never user input)
     * decides which one, so this stays outside the allow-list-vs-raw-input
     * concern the rest of this class is about.
     */
    private function applySearch(Builder $query, mixed $search): void
    {
        if (! is_string($search) || trim($search) === '') {
            return;
        }

        $needle = '%'.mb_strtolower($this->escapeLike(trim($search))).'%';
        $castType = DB::connection()->getDriverName() === 'sqlite' ? 'TEXT' : 'CHAR';

        $query->where(function (Builder $inner) use ($needle, $castType): void {
            $inner->whereRaw("LOWER(CAST(mapped_values AS {$castType})) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(CAST(extra_values AS {$castType})) LIKE ?", [$needle]);
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
