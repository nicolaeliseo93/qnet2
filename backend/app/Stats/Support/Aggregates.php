<?php

declare(strict_types=1);

namespace App\Stats\Support;

use App\Stats\Widgets\DistributionItem;
use App\Stats\Widgets\TrendPoint;
use BackedEnum;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The aggregate query toolbox every StatsDefinition composes (spec 0026).
 *
 * Every KPI is a single SQL aggregate (COUNT/SUM/AVG + GROUP BY, LIMIT
 * server-side): no collection is ever materialized in memory and no "top N"
 * is sliced in PHP. Table/column names always come from definition constants,
 * never from request input, so the few raw fragments below (COUNT(*), the
 * driver's month expression) carry no injection surface.
 */
final class Aggregates
{
    private const string COUNT_ALIAS = 'aggregate';

    private const string BUCKET_ALIAS = 'bucket';

    /**
     * Top-N breakdown of `$query` (a query on the OWNING table) grouped by a
     * belongs-to relation, labelled with the related row's own column.
     *
     * @param  Builder  $query  base query on the owning table (or pivot table)
     * @param  string|Expression  $foreignKey  qualified FK (`leads.source_id`) or a
     *                                         static SQL expression built from
     *                                         definition constants (never input)
     * @param  string  $labelColumn  related label column, e.g. `name`
     * @param  string|null  $colorColumn  related color column, when the domain has one
     * @return array<int, DistributionItem>
     */
    public static function topRelated(
        Builder $query,
        string|Expression $foreignKey,
        string $relatedTable,
        string $labelColumn,
        int $limit,
        ?string $colorColumn = null,
    ): array {
        $columns = ["{$relatedTable}.id", "{$relatedTable}.{$labelColumn}"];

        if ($colorColumn !== null) {
            $columns[] = "{$relatedTable}.{$colorColumn}";
        }

        $rows = $query
            ->join($relatedTable, "{$relatedTable}.id", '=', $foreignKey)
            ->select($columns)
            ->selectRaw('COUNT(*) as '.self::COUNT_ALIAS)
            ->groupBy($columns)
            ->orderByDesc(self::COUNT_ALIAS)
            ->limit($limit)
            ->get();

        return $rows
            ->map(static fn (object $row): DistributionItem => new DistributionItem(
                key: (string) $row->id,
                label: (string) $row->{$labelColumn},
                value: (int) $row->{self::COUNT_ALIAS},
                color: $colorColumn === null ? null : self::nullableString($row->{$colorColumn}),
            ))
            ->all();
    }

    /**
     * Breakdown by a plain (enum-backed) column of `$table`. NULL rows are
     * excluded from the items — they are still part of the widget's `total`
     * denominator, which the caller passes in. Item labels/colors come from
     * the enum's presentation metadata (App\Enums\Concerns\HasMeta), falling
     * back to the raw stored value.
     *
     * @param  class-string<BackedEnum>  $enum
     * @return array<int, DistributionItem>
     */
    public static function byEnumColumn(string $table, string $column, string $enum): array
    {
        $rows = DB::table($table)
            ->whereNotNull($column)
            ->select($column)
            ->selectRaw('COUNT(*) as '.self::COUNT_ALIAS)
            ->groupBy($column)
            ->orderByDesc(self::COUNT_ALIAS)
            ->get();

        return $rows
            ->map(static function (object $row) use ($column, $enum): DistributionItem {
                $value = (string) $row->{$column};
                $case = $enum::tryFrom($value);

                return new DistributionItem(
                    key: $value,
                    label: $case === null ? $value : $case->label(),
                    value: (int) $row->{self::COUNT_ALIAS},
                    color: $case?->color(),
                );
            })
            ->all();
    }

    /**
     * Dense month-by-month row count over the last `$months` buckets (empty
     * months included with 0), keyed `YYYY-MM`.
     *
     * @return array<int, TrendPoint>
     */
    public static function monthlyTrend(string $table, string $column, int $months): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $expression = self::monthExpression("{$table}.{$column}");

        $counts = DB::table($table)
            ->where("{$table}.{$column}", '>=', $start->toDateString())
            ->selectRaw("{$expression} as ".self::BUCKET_ALIAS)
            ->selectRaw('COUNT(*) as '.self::COUNT_ALIAS)
            ->groupBy(DB::raw($expression))
            ->pluck(self::COUNT_ALIAS, self::BUCKET_ALIAS);

        $points = [];

        for ($offset = 0; $offset < $months; $offset++) {
            $bucket = $start->copy()->addMonths($offset)->format('Y-m');

            $points[] = new TrendPoint($bucket, (int) ($counts[$bucket] ?? 0));
        }

        return $points;
    }

    /**
     * The driver-specific `YYYY-MM` bucket expression. `$column` is always a
     * definition constant, never request input.
     */
    private static function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
