<?php

namespace App\Tables;

use App\Models\CompanySite;
use App\Models\User;
use App\Tables\CompanySites\CompanySiteAddressColumns;
use App\Tables\CompanySites\CompanySiteColumnCatalog;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `company-sites` domain (spec 0020).
 *
 * Real columns (id, is_default, name, created_at) are handled entirely by the
 * generic engine. `primary_contact` is COMPUTED from the card's eager-loaded
 * contacts via the shared PrimaryContactColumn (display-only, like the Registry
 * grid). The 4 address-derived columns (city/region/province/postal_code) have
 * no real DB column of their own — resolved from the CARD's primary address
 * (CompanySite → personalData → addresses) by the CompanySiteAddressColumns
 * collaborator.
 */
class CompanySitesTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly CompanySiteAddressColumns $addressColumns,
        private readonly PrimaryContactColumn $contactColumn,
    ) {}

    public function domain(): string
    {
        return 'company-sites';
    }

    /**
     * @return class-string<CompanySite>
     */
    public function modelClass(): string
    {
        return CompanySite::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe
    // default in AbstractTableDefinition derives CompanySitePolicy::viewAny
    // from modelClass() (company-sites.viewAny).

    /**
     * @return Builder<CompanySite>
     */
    public function baseQuery(): Builder
    {
        // Eager-load the card's contacts and ONLY its primary address (+ geo
        // relations), plus the logo, so mapRow reads every derived field
        // entirely from memory — a fixed number of queries regardless of row
        // count.
        return CompanySite::query()->with([
            'personalData.contacts',
            'personalData.addresses' => function ($query): void {
                $query->where('is_primary', true)
                    ->with(['state:id,name', 'province:id,name', 'city:id,name']);
            },
            'logo',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return CompanySiteColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return CompanySiteColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return CompanySiteColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Dynamic geo set-filter options (spec 0004/0005), delegated to the
     * collaborator.
     *
     * @return array<int, scalar>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        return $this->addressColumns->isGeoColumn($columnId)
            ? $this->addressColumns->options($columnId)
            : null;
    }

    /**
     * Map a CompanySite to the row payload (real fields + the card's primary
     * contacts + the card's primary address' derived geo/postal_code fields +
     * the logo). `actions` is attached by the generic TableService via
     * actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var CompanySite $row */
        $card = $row->personalData;
        $address = $card?->addresses->first();

        return [
            'id' => $row->id,
            'is_default' => $row->is_default,
            'name' => $row->name,
            'primary_contact' => $this->contactColumn->format($card?->contacts),
            'city' => $address?->city?->name,
            'province' => $address?->province?->name,
            'region' => $address?->state?->name,
            'postal_code' => $address?->postal_code,
            'created_at' => $row->created_at,
            'logo_url' => $row->logoDataUri(),
        ];
    }

    /**
     * Allowed action keys for a single row, computed via CompanySitePolicy.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = [];

        if (Gate::forUser($actor)->allows('view', $row)) {
            $allowed[] = 'view';
        }

        if (Gate::forUser($actor)->allows('update', $row)) {
            $allowed[] = 'edit';
        }

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        return $allowed;
    }

    /**
     * Handle the derived filters (no real DB column) via
     * CompanySiteAddressColumns: the 3 geo set filters and the `postal_code`
     * text filter.
     *
     * @param  Builder<CompanySite>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($this->addressColumns->isGeoColumn($columnId)) {
            $this->addressColumns->applyFilter($query, $columnId, $filter);

            return true;
        }

        if ($this->addressColumns->isPostalCodeColumn($columnId)) {
            $this->addressColumns->applyPostalCodeFilter($query, $filter);

            return true;
        }

        return false;
    }

    /**
     * ORDER BY a derived (address-backed) column via a correlated subquery, so
     * sorting never needs a row-multiplying JOIN on the main company_sites query.
     *
     * @param  Builder<CompanySite>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if ($this->addressColumns->isGeoColumn($columnId)) {
            $query->orderBy($this->addressColumns->sortSubquery($columnId), $direction);

            return true;
        }

        if ($this->addressColumns->isPostalCodeColumn($columnId)) {
            $query->orderBy($this->addressColumns->postalCodeSortSubquery(), $direction);

            return true;
        }

        return false;
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the 3 geo columns.
     * `postal_code` declares `hasFilterValues=false`, so TableService never
     * calls this method for it. Every other (real-column) filterable column
     * falls through to the generic engine's `SELECT DISTINCT` (return null) —
     * including `is_default`.
     *
     * @param  Builder<CompanySite>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return $this->addressColumns->isGeoColumn($columnId)
            ? $this->addressColumns->distinctValues($columnId, $search, $limit)
            : null;
    }
}
