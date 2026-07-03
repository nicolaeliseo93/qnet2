<?php

namespace App\Tables\Users;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\Address;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The 9 employment-derived grid columns on the `users` table (spec 0015):
 * business_function/company/reports_to (related NAMES), relationship_type/
 * qualification_type (enums), is_manager (boolean), hired_at/terminated_at
 * (dates) and operational_site (formatted address line, CONDITIONS-ONLY —
 * mirrors `primary_address`, spec 0005 UX decision).
 *
 * None of these has a real column on `users`: every filter/sort/distinct-
 * values resolution goes through `employment` (a hasOne), matched via
 * whereHas + a correlated sort subquery — mirroring UserGeoColumns/
 * UserPersonalDataColumns. Set/date filters delegate to the shared
 * FilterApplier (bound parameters, same operator set as real columns) so no
 * filter logic is duplicated for the two date columns.
 *
 * Extracted out of UsersTableDefinition (file-size split, engineering.md §6).
 */
class UserEmploymentColumns
{
    /**
     * Maximum number of values honoured in a set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Column id -> [related table, name column, relation path for whereHas].
     *
     * @var array<string, array{table: string, nameColumn: string, relation: string}>
     */
    private const array RELATED_NAME_COLUMNS = [
        'business_function' => ['table' => 'business_functions', 'nameColumn' => 'name', 'relation' => 'employment.businessFunction'],
        'company' => ['table' => 'companies', 'nameColumn' => 'denomination', 'relation' => 'employment.company'],
        'reports_to' => ['table' => 'users', 'nameColumn' => 'name', 'relation' => 'employment.reportsTo'],
    ];

    private const array ENUM_COLUMNS = ['relationship_type', 'qualification_type'];

    private const array DATE_COLUMNS = ['hired_at', 'terminated_at'];

    public function __construct(private readonly FilterApplier $filterApplier) {}

    public function isEmploymentColumn(string $columnId): bool
    {
        return array_key_exists($columnId, self::RELATED_NAME_COLUMNS)
            || in_array($columnId, self::ENUM_COLUMNS, true)
            || in_array($columnId, self::DATE_COLUMNS, true)
            || $columnId === 'is_manager'
            || $columnId === 'operational_site';
    }

    /**
     * Row fields derived from the eager-loaded employment profile.
     *
     * @return array<string, mixed>
     */
    public function mapRow(?EmploymentProfile $employment): array
    {
        return [
            'business_function' => $employment?->businessFunction?->name,
            'company' => $employment?->company?->denomination,
            'operational_site' => $this->operationalSiteLabel($employment?->operationalSite),
            'relationship_type' => $employment?->relationship_type?->value,
            'qualification_type' => $employment?->qualification_type?->value,
            'is_manager' => $employment?->is_manager ?? false,
            'reports_to' => $employment?->reportsTo?->name,
            'hired_at' => $employment?->hired_at,
            'terminated_at' => $employment?->terminated_at,
        ];
    }

    /**
     * The snake_case enum key (config/config.php form_enums) for the two
     * enum columns, so the frontend localizes their options from its own
     * i18n `enums.*` namespace.
     */
    public function enumKeyFor(string $columnId): ?string
    {
        return in_array($columnId, self::ENUM_COLUMNS, true) ? $columnId : null;
    }

    /**
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $filter): void
    {
        if (array_key_exists($columnId, self::RELATED_NAME_COLUMNS)) {
            $this->filterByRelatedName($query, $columnId, $filter);

            return;
        }

        if (in_array($columnId, self::ENUM_COLUMNS, true) || $columnId === 'is_manager') {
            $query->whereHas('employment', fn (Builder $q): mixed => $this->filterApplier->apply($q, $columnId, ['filterType' => 'set'], $filter));

            return;
        }

        if (in_array($columnId, self::DATE_COLUMNS, true)) {
            $query->whereHas('employment', fn (Builder $q): mixed => $this->filterApplier->apply($q, $columnId, ['filterType' => 'date'], $filter));

            return;
        }

        if ($columnId === 'operational_site') {
            $this->applyOperationalSiteFilter($query, $filter);
        }
    }

    /**
     * Derived set filter via a nested whereHas on the related NAME column,
     * bound + capped cardinality (mirrors BusinessFunctionsTableDefinition's
     * `manager`/`users` filters).
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterByRelatedName(Builder $query, string $columnId, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names === []) {
            return;
        }

        $column = self::RELATED_NAME_COLUMNS[$columnId];

        $query->whereHas($column['relation'], static function (Builder $relatedQuery) use ($column, $names): void {
            $relatedQuery->whereIn($column['nameColumn'], $names);
        });
    }

    /**
     * `operational_site` CONDITIONS-ONLY text filter: bound LIKE on the site's
     * primary address street/postal/city-name (mirrors UserPersonalDataColumns
     * ::applyAddressFilter — no Set/checklist, spec 0005 UX decision, hence
     * declared `hasFilterValues:false` on the column).
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function applyOperationalSiteFilter(Builder $query, array $filter): void
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return;
        }

        $needle = '%'.$this->filterApplier->escapeLike((string) $value).'%';

        $query->whereHas('employment.operationalSite.addresses', static function (Builder $addressQuery) use ($needle): void {
            $addressQuery->where('is_primary', true)
                ->where(function (Builder $match) use ($needle): void {
                    $match->where('line1', 'like', $needle)
                        ->orWhere('postal_code', 'like', $needle)
                        ->orWhereHas('city', static function (Builder $cityQuery) use ($needle): void {
                            $cityQuery->where('name', 'like', $needle);
                        });
                });
        });
    }

    /**
     * ORDER BY the employment-derived value via a correlated subquery scoped
     * to `employment_profiles.user_id`, so sorting never needs a row-
     * multiplying JOIN (employment is truly 1:1, but every related name is a
     * further hop away).
     *
     * @return Builder<Model>|null
     */
    public function sortSubquery(string $columnId): ?Builder
    {
        if (array_key_exists($columnId, self::RELATED_NAME_COLUMNS)) {
            return $this->relatedNameSortSubquery($columnId);
        }

        if (in_array($columnId, self::ENUM_COLUMNS, true) || $columnId === 'is_manager' || in_array($columnId, self::DATE_COLUMNS, true)) {
            return EmploymentProfile::query()
                ->select($columnId)
                ->whereColumn('employment_profiles.user_id', 'users.id')
                ->limit(1);
        }

        if ($columnId === 'operational_site') {
            return $this->correlateToEmployment(
                Address::query()
                    ->select('addresses.line1')
                    ->join('employment_profiles', 'employment_profiles.operational_site_id', '=', 'addresses.addressable_id')
                    ->where('addresses.addressable_type', (new OperationalSite)->getMorphClass())
                    ->where('addresses.is_primary', true),
            );
        }

        return null;
    }

    /**
     * @return Builder<Model>
     */
    private function relatedNameSortSubquery(string $columnId): Builder
    {
        $column = self::RELATED_NAME_COLUMNS[$columnId];
        $foreignKey = $this->foreignKeyFor($columnId);
        // `reports_to` self-joins `users` (the outer query's own table), so it
        // needs an alias; the other two related tables never collide.
        $joinTarget = $columnId === 'reports_to' ? "{$column['table']} as employment_reports_to" : $column['table'];
        $joinAlias = $columnId === 'reports_to' ? 'employment_reports_to' : $column['table'];

        return EmploymentProfile::query()
            ->select("{$joinAlias}.{$column['nameColumn']}")
            ->join($joinTarget, "{$joinAlias}.id", '=', "employment_profiles.{$foreignKey}")
            ->whereColumn('employment_profiles.user_id', 'users.id')
            ->limit(1);
    }

    private function foreignKeyFor(string $columnId): string
    {
        return match ($columnId) {
            'business_function' => 'business_function_id',
            'company' => 'company_id',
            'reports_to' => 'reports_to_id',
            default => throw new InvalidArgumentException("Unknown related-name column [{$columnId}]."),
        };
    }

    /**
     * @param  Builder<Model>  $subquery
     * @return Builder<Model>
     */
    private function correlateToEmployment(Builder $subquery): Builder
    {
        return $subquery->whereColumn('employment_profiles.user_id', 'users.id')->limit(1);
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the 6 columns that have
     * one (business_function/company/reports_to/relationship_type/
     * qualification_type/is_manager). `operational_site`/`hired_at`/
     * `terminated_at` declare `hasFilterValues:false` (UserColumnCatalog), so
     * TableService never calls this method for them.
     *
     * @param  Builder<User>  $query
     * @return array<int, scalar>
     */
    public function distinctValues(Builder $query, string $columnId, ?string $search, int $limit): array
    {
        if (array_key_exists($columnId, self::RELATED_NAME_COLUMNS)) {
            return $this->distinctRelatedNames($query, $columnId, $search, $limit);
        }

        if ($columnId === 'relationship_type') {
            return $this->distinctEnumValues(RelationshipTypeEnum::values(), $search, $limit);
        }

        if ($columnId === 'qualification_type') {
            return $this->distinctEnumValues(QualificationTypeEnum::values(), $search, $limit);
        }

        if ($columnId === 'is_manager') {
            return [true, false];
        }

        return [];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function distinctEnumValues(array $values, ?string $search, int $limit): array
    {
        $matches = $search === null || $search === ''
            ? $values
            : array_values(array_filter($values, static fn (string $value): bool => stripos($value, $search) !== false));

        return array_slice($matches, 0, $limit);
    }

    /**
     * @param  Builder<User>  $query
     * @return array<int, string>
     */
    private function distinctRelatedNames(Builder $query, string $columnId, ?string $search, int $limit): array
    {
        $column = self::RELATED_NAME_COLUMNS[$columnId];
        $foreignKey = $this->foreignKeyFor($columnId);

        $userIds = (clone $query)->select('users.id');

        $relatedIds = DB::table('employment_profiles')
            ->select($foreignKey)
            ->whereIn('user_id', $userIds)
            ->whereNotNull($foreignKey);

        return DB::table($column['table'])
            ->whereIn('id', $relatedIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search, $column): void {
                $builder->where($column['nameColumn'], 'like', '%'.$this->filterApplier->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy($column['nameColumn'])
            ->limit($limit)
            ->pluck($column['nameColumn'])
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Format the operational site's primary address as "line1[- city]", or
     * null when the site/address is missing (spec 0015 label contract).
     */
    private function operationalSiteLabel(?OperationalSite $site): ?string
    {
        $address = $site?->primaryAddress;

        if ($address === null) {
            return null;
        }

        $city = $address->city?->name;

        return $city !== null ? "{$address->line1} - {$city}" : $address->line1;
    }
}
