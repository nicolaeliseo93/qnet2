<?php

namespace App\Tables;

use App\Enums\LocaleEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\Address;
use App\Models\City;
use App\Models\Contact;
use App\Models\Country;
use App\Models\PersonalData;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `users` domain — the only concrete definition today.
 *
 * Carries what config/tables/users.php + the users-specific half of
 * UserTableService carried before: real User columns (id, name, email,
 * roles[derived], locale, created_at), the `roles` set filter with DYNAMIC
 * options resolved via UserService::assignableRoleNames($actor) (so a non
 * super-admin never sees `super-admin`), mapRow returning the same real fields
 * (never password/remember_token), and actionsFor calling UserPolicy
 * (view→view, update→edit, delete→delete; delete already forbids self-delete).
 *
 * Only REAL columns of the `users` table are exposed (id, name, email, locale,
 * created_at) plus the derived `roles` field. `sortable`/`filterable` below are
 * the server-side whitelist enforced by TableRowsRequest + TableService.
 */
class UsersTableDefinition extends AbstractTableDefinition
{
    /**
     * Maximum number of role names honoured in the `roles` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_ROLE_FILTER_VALUES = 50;

    /**
     * Maximum number of values honoured in a geo set filter (country/region/
     * province/city). Caps the WHERE IN cardinality; excess values are ignored.
     */
    private const int MAX_GEO_FILTER_VALUES = 200;

    /**
     * Geo column id → [belongsTo relation on Address, model class]. Single source
     * of truth for both option resolution (optionsFor) and the derived set filter
     * (applyDerivedFilter): the geo NAME is used as BOTH the option token and the
     * match column, so there is never an id/label mismatch. Note `region` maps to
     * the State model (a State is a region/regione in this geo hierarchy).
     *
     * @var array<string, array{relation: string, model: class-string}>
     */
    private const array GEO_COLUMNS = [
        'country' => ['relation' => 'country', 'model' => Country::class],
        'region' => ['relation' => 'state', 'model' => State::class],
        'province' => ['relation' => 'province', 'model' => Province::class],
        'city' => ['relation' => 'city', 'model' => City::class],
    ];

    public function __construct(private readonly UserService $userService) {}

    public function domain(): string
    {
        return 'users';
    }

    /**
     * @return class-string<User>
     */
    public function modelClass(): string
    {
        return User::class;
    }

    // authorizeViewAny() is intentionally NOT overridden: the fail-safe default
    // in AbstractTableDefinition already derives UserPolicy::viewAny from
    // modelClass() (users.viewAny), so the explicit override was redundant.

    /**
     * @return Builder<User>
     */
    public function baseQuery(): Builder
    {
        // Eager-load roles and the avatar relation to avoid N+1 when each row
        // resolves its role names and avatar URL. The personalData card is loaded
        // with ONLY its primary address (+ geo relations) and primary contact, so
        // mapRow reads the user_type/address/geo/contact columns entirely from
        // memory — a fixed number of queries regardless of row count.
        return User::query()
            ->with('roles', 'avatar')
            ->with([
                'personalData.addresses' => function ($query): void {
                    $query->where('is_primary', true)
                        ->with([
                            'country:id,name',
                            'state:id,name',
                            'province:id,name',
                            'city:id,name',
                        ]);
                },
                'personalData.contacts' => function ($query): void {
                    $query->where('is_primary', true);
                },
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            [
                'id' => 'id',
                'label' => 'users.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => false,
                'filterType' => null,
            ],
            [
                // Avatar, embedded inline as a data: URI; the frontend renders
                // it via a custom avatar cell (not sortable nor filterable — it
                // is a derived value, not a real column).
                'id' => 'avatar_url',
                'label' => 'users.columns.avatar',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'filterType' => null,
            ],
            [
                'id' => 'name',
                'label' => 'users.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'email',
                'label' => 'users.columns.email',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'roles',
                'label' => 'users.columns.roles',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'locale',
                'label' => 'users.columns.locale',
                'type' => 'enum',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => LocaleEnum::values(),
            ],
            [
                'id' => 'created_at',
                'label' => 'users.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                // Person vs company, derived from personalData.type. Rendered as a
                // BADGE whose label/color/icon come entirely from the enum (see
                // badgesFor). Sortable via a correlated subquery (applyDerivedSort).
                'id' => 'user_type',
                'label' => 'users.columns.user_type',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => self::personalDataTypeValues(),
            ],
            [
                // Pre-formatted primary address string (is_primary), derived from
                // personalData.addresses. Text filter via whereHas LIKE; sorted by
                // the primary address line via a correlated subquery.
                'id' => 'primary_address',
                'label' => 'users.columns.primary_address',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                // Geo name from the primary address. Hidden by default; set filter
                // with backend-resolved options (distinct names in use). Sorted by
                // the related geo name via a correlated subquery.
                'id' => 'country',
                'label' => 'users.columns.country',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'region',
                'label' => 'users.columns.region',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'province',
                'label' => 'users.columns.province',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'city',
                'label' => 'users.columns.city',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // ALL primary contacts (one per type: phone, email, …) derived
                // from personalData.contacts where is_primary. Rendered as tags
                // (an array, like roles); the text filter matches ANY primary
                // contact of ANY type via whereHas LIKE. Sorted by the first
                // primary contact value (MIN) via a correlated subquery.
                'id' => 'primary_contact',
                'label' => 'users.columns.primary_contact',
                'type' => 'tags',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'email', 'type' => 'text'],
            ['columnId' => 'roles', 'type' => 'set'],
            ['columnId' => 'locale', 'type' => 'set', 'options' => LocaleEnum::values()],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'user_type', 'type' => 'set', 'options' => self::personalDataTypeValues()],
            ['columnId' => 'primary_address', 'type' => 'text'],
            // Geo set filters: options resolved dynamically in optionsFor().
            ['columnId' => 'country', 'type' => 'set'],
            ['columnId' => 'region', 'type' => 'set'],
            ['columnId' => 'province', 'type' => 'set'],
            ['columnId' => 'city', 'type' => 'set'],
            ['columnId' => 'primary_contact', 'type' => 'text'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'users.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'users.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'users.delete',
            ],
        ];
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
     * Dynamic `roles` options: only roles the actor may actually assign are
     * offered, so a non super-admin never sees `super-admin` (affordance ↔
     * authorization coherence). UserService remains the source of truth.
     *
     * @return array<int, scalar>|null
     */
    protected function optionsFor(string $columnId, User $actor): ?array
    {
        if ($columnId === 'roles') {
            return $this->userService->assignableRoleNames($actor);
        }

        if (array_key_exists($columnId, self::GEO_COLUMNS)) {
            return $this->geoOptions($columnId);
        }

        return null;
    }

    /**
     * Distinct geo NAMES actually present among users' primary addresses, sorted.
     *
     * Resolving from values-in-use (rather than the full geo tables, which can be
     * huge — e.g. every city) keeps the set-filter option list small and relevant.
     * The name is the option token AND the column matched in applyDerivedFilter,
     * so token and match never drift. Runs once per GET /columns, not per row.
     *
     * @return array<int, string>
     */
    private function geoOptions(string $columnId): array
    {
        [$relation, $model] = [self::GEO_COLUMNS[$columnId]['relation'], self::GEO_COLUMNS[$columnId]['model']];
        $foreignKey = "{$relation}_id";

        return $model::query()
            ->whereIn('id', function ($query) use ($foreignKey): void {
                $query->select($foreignKey)
                    ->from('addresses')
                    ->where('is_primary', true)
                    ->whereNotNull($foreignKey)
                    // Morph alias (enforced morphMap), not the FQCN.
                    ->where('addressable_type', (new PersonalData)->getMorphClass());
            })
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Badge metadata for the `user_type` column: value/label/color/icon for each
     * PersonalDataTypeEnum case, so the frontend renders the badge without any
     * knowledge of the User domain.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        if ($columnId === 'user_type') {
            return array_map(
                static fn ($meta): array => $meta->toArray(),
                PersonalDataTypeEnum::options(),
            );
        }

        return null;
    }

    /**
     * The `user_type` badge is driven by PersonalDataTypeEnum, exposed to the
     * frontend config under the `personal_data_type` enum key (config/config.php
     * → form_enums). Declaring it lets the frontend localize the badge label from
     * its i18n resources instead of the backend label.
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'user_type' ? 'personal_data_type' : null;
    }

    /**
     * Map a User model to the row payload (real fields + derived roles only).
     * No hidden fields are ever exposed (password/remember_token). `actions` is
     * attached by the generic TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var User $row */
        $card = $row->personalData;
        $address = $card?->addresses->first();

        return [
            'id' => $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'avatar_url' => $row->avatarDataUri(),
            'roles' => $row->getRoleNames()->all(),
            'locale' => $row->locale,
            'created_at' => $row->created_at,
            // Derived from the personalData card. null (→ em-dash on the frontend)
            // when the user has no card or no primary address/contact. The raw
            // sensitive fields (line1, contact value) are read server-side ONLY to
            // build the formatted string; they are never returned as row fields.
            'user_type' => $card?->type?->value,
            'primary_address' => $this->formatAddress($address),
            'country' => $address?->country?->name,
            'region' => $address?->state?->name,
            'province' => $address?->province?->name,
            'city' => $address?->city?->name,
            // ALL primary contacts (one per type), as an array of formatted
            // strings. Empty array (→ em-dash on the frontend) when there is none.
            'primary_contact' => $this->formatContacts($card?->contacts),
        ];
    }

    /**
     * Format the primary address as a single human-readable line, e.g.
     * "Via Roma 12, 20100 Milano (MI)". Returns null when there is no address.
     */
    private function formatAddress(?Address $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $street = trim((string) ($address->line1 ?? ''));
        $locality = trim(implode(' ', array_filter([
            $address->postal_code,
            $address->city?->name,
        ])));
        $province = $address->province?->name;

        if ($province !== null && $locality !== '') {
            $locality .= " ({$province})";
        }

        $line = trim(implode(', ', array_filter([$street ?: null, $locality ?: null])));

        return $line === '' ? null : $line;
    }

    /**
     * Structured payload for EVERY primary contact (one per type), so the
     * frontend can render each as a badge with the contact-type icon + label
     * without knowing the ContactType domain. Empty array when there is none
     * (the collection is already constrained to is_primary by baseQuery).
     *
     * @param  Collection<int, Contact>|null  $contacts
     * @return array<int, array{type: string, icon: string|null, label: string, value: string}>
     */
    private function formatContacts(?Collection $contacts): array
    {
        if ($contacts === null) {
            return [];
        }

        return $contacts
            ->map(fn (Contact $contact): ?array => $this->formatContact($contact))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Structured payload for a single contact: its type, the type's icon (from
     * the enum metadata), a display label (the contact's own label when set, else
     * the type label) and the value. Returns null when the value is empty.
     *
     * @return array{type: string, icon: string|null, label: string, value: string}|null
     */
    private function formatContact(Contact $contact): ?array
    {
        $value = trim((string) ($contact->value ?? ''));

        if ($value === '') {
            return null;
        }

        $type = $contact->type;
        $label = trim((string) ($contact->label ?? ''));

        return [
            'type' => $type->value,
            'icon' => $type->icon(),
            'label' => $label !== '' ? $label : $type->label(),
            'value' => $value,
        ];
    }

    /**
     * Plain string[] of the PersonalDataTypeEnum values, used both as the
     * `user_type` set-filter options and (via badgesFor) the badge value tokens.
     *
     * @return array<int, string>
     */
    private static function personalDataTypeValues(): array
    {
        return array_map(
            static fn (PersonalDataTypeEnum $case): string => $case->value,
            PersonalDataTypeEnum::cases(),
        );
    }

    /**
     * Allowed action keys for a single row, computed via UserPolicy.
     * This is the per-row source of truth reflected in row.actions[].
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
     * Handle the derived `roles` set filter (no real DB column) via whereHas on
     * the Spatie role relationship. Lifted verbatim from the old
     * UserTableService::applyRolesFilter: only string names, capped cardinality,
     * bound parameters — names are never inlined into SQL.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if (array_key_exists($columnId, self::GEO_COLUMNS)) {
            $this->filterGeo($query, $columnId, $filter);

            return true;
        }

        return match ($columnId) {
            'roles' => $this->filterRoles($query, $filter),
            'user_type' => $this->filterUserType($query, $filter),
            'primary_address' => $this->filterPrimaryAddressText($query, $filter),
            'primary_contact' => $this->filterPrimaryContactText($query, $filter),
            default => false,
        };
    }

    /**
     * ORDER BY a derived (relation-backed) column via a correlated subquery, so
     * sorting never needs a row-multiplying JOIN on the main users query nor
     * pollutes its `users.*` selection. Each subquery yields a single scalar per
     * user (the geo name / address line / type / first contact value), or NULL
     * when the user has no card or no primary row.
     *
     * @param  Builder<User>  $query
     */
    public function applyDerivedSort(Builder $query, string $columnId, string $direction): bool
    {
        if (array_key_exists($columnId, self::GEO_COLUMNS)) {
            $query->orderBy($this->geoSortSubquery($columnId), $direction);

            return true;
        }

        $subquery = match ($columnId) {
            'user_type' => $this->correlateToUser(
                PersonalData::query()->select('personal_data.type'),
            ),
            'primary_address' => $this->correlateToUser(
                Address::query()
                    ->select('addresses.line1')
                    ->join('personal_data', 'personal_data.id', '=', 'addresses.addressable_id')
                    ->where('addresses.addressable_type', (new PersonalData)->getMorphClass())
                    ->where('addresses.is_primary', true),
            ),
            'primary_contact' => $this->correlateToUser(
                Contact::query()
                    ->selectRaw('min(contacts.value)')
                    ->join('personal_data', 'personal_data.id', '=', 'contacts.contactable_id')
                    ->where('contacts.contactable_type', (new PersonalData)->getMorphClass())
                    ->where('contacts.is_primary', true),
            ),
            default => null,
        };

        if ($subquery === null) {
            return false;
        }

        $query->orderBy($subquery, $direction);

        return true;
    }

    /**
     * Subquery selecting the primary address' related geo NAME (country/region/
     * province/city) for a user, used as the ORDER BY key for that geo column.
     *
     * @return Builder<Model>
     */
    private function geoSortSubquery(string $columnId): Builder
    {
        [$relation, $model] = [self::GEO_COLUMNS[$columnId]['relation'], self::GEO_COLUMNS[$columnId]['model']];
        $geoTable = (new $model)->getTable();
        $foreignKey = "{$relation}_id";

        return $this->correlateToUser(
            $model::query()
                ->select("{$geoTable}.name")
                ->join('addresses', "addresses.{$foreignKey}", '=', "{$geoTable}.id")
                ->join('personal_data', 'personal_data.id', '=', 'addresses.addressable_id')
                ->where('addresses.addressable_type', (new PersonalData)->getMorphClass())
                ->where('addresses.is_primary', true),
        );
    }

    /**
     * Correlate a subquery (already joined to / based on `personal_data`) to the
     * outer user and limit it to a single row, so it is usable as an ORDER BY
     * scalar. `personable_type` uses the morph alias (enforced morphMap).
     *
     * @param  Builder<Model>  $subquery
     * @return Builder<Model>
     */
    private function correlateToUser(Builder $subquery): Builder
    {
        return $subquery
            ->whereColumn('personal_data.personable_id', 'users.id')
            ->where('personal_data.personable_type', (new User)->getMorphClass())
            ->limit(1);
    }

    /**
     * Derived `roles` set filter via whereHas on the Spatie role relationship.
     * Only string names, capped cardinality, bound parameters — never inlined.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterRoles(Builder $query, array $filter): bool
    {
        $roles = $this->setFilterValues($filter, self::MAX_ROLE_FILTER_VALUES);

        if ($roles !== []) {
            $query->whereHas('roles', static function (Builder $roleQuery) use ($roles): void {
                $roleQuery->whereIn('name', $roles);
            });
        }

        return true;
    }

    /**
     * Derived `user_type` set filter on personalData.type. Only valid enum values
     * are honoured; whereHas implicitly excludes users without a card.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterUserType(Builder $query, array $filter): bool
    {
        $values = array_values(array_filter(
            $this->setFilterValues($filter, self::MAX_GEO_FILTER_VALUES),
            static fn (string $value): bool => PersonalDataTypeEnum::tryFrom($value) !== null,
        ));

        if ($values !== []) {
            $query->whereHas('personalData', static function (Builder $cardQuery) use ($values): void {
                $cardQuery->whereIn('type', $values);
            });
        }

        return true;
    }

    /**
     * Derived geo set filter (country/region/province/city) matched by NAME on the
     * related geo table, scoped to the user's PRIMARY address. Bound parameters.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterGeo(Builder $query, string $columnId, array $filter): void
    {
        $names = $this->setFilterValues($filter, self::MAX_GEO_FILTER_VALUES);

        if ($names === []) {
            return;
        }

        $relation = self::GEO_COLUMNS[$columnId]['relation'];

        $query->whereHas('personalData.addresses', static function (Builder $addressQuery) use ($relation, $names): void {
            $addressQuery->where('is_primary', true)
                ->whereHas($relation, static function (Builder $geoQuery) use ($names): void {
                    $geoQuery->whereIn('name', $names);
                });
        });
    }

    /**
     * Derived `primary_address` text filter: bound LIKE on the primary address'
     * street/postal/city-name. Wildcards in user input are escaped.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterPrimaryAddressText(Builder $query, array $filter): bool
    {
        $needle = $this->likeNeedle($filter);

        if ($needle !== null) {
            $query->whereHas('personalData.addresses', function (Builder $addressQuery) use ($needle): void {
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

        return true;
    }

    /**
     * Derived `primary_contact` text filter: bound LIKE on the primary contact'
     * value/label. Wildcards in user input are escaped.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    private function filterPrimaryContactText(Builder $query, array $filter): bool
    {
        $needle = $this->likeNeedle($filter);

        if ($needle !== null) {
            $query->whereHas('personalData.contacts', function (Builder $contactQuery) use ($needle): void {
                $contactQuery->where('is_primary', true)
                    ->where(function (Builder $match) use ($needle): void {
                        $match->where('value', 'like', $needle)
                            ->orWhere('label', 'like', $needle);
                    });
            });
        }

        return true;
    }

    /**
     * Extract, sanitize and cap the string values of a set filter payload.
     *
     * @param  array<string, mixed>  $filter
     * @return array<int, string>
     */
    private function setFilterValues(array $filter, int $max): array
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return [];
        }

        $clean = array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        ));

        return array_slice($clean, 0, $max);
    }

    /**
     * Build a bound `%needle%` LIKE pattern from a text filter, or null when the
     * filter carries no usable value. Wildcards are escaped so they are literal.
     *
     * @param  array<string, mixed>  $filter
     */
    private function likeNeedle(array $filter): ?string
    {
        $value = $filter['filter'] ?? null;

        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return '%'.$this->escapeLike((string) $value).'%';
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally. Mirrors
     * TableService::escapeLike (kept local to avoid widening that class' API).
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
